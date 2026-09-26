<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pregunta al portal por HTTP, de servidor a servidor.
 *
 * Una llamada por petición, a propósito: así quitar un permiso en el portal
 * surte efecto al momento en todas las aplicaciones. Es barata (una fila por
 * persona en el portal) y, cuando alguien usa una aplicación, el portal suele
 * estar despierto porque acaba de pasar por él para entrar.
 */
final readonly class HttpPortal implements Portal
{
    /**
     * Cloud Run despierta un servicio dormido en unos segundos; más de esto ya
     * no es un arranque en frío, es que algo va mal.
     */
    private const float TIMEOUT = 15.0;

    /**
     * @param string $portalUrl   dónde está el portal visto desde ESTE servidor
     *                            (en desarrollo, http://host.docker.internal:8083)
     * @param string $application el código de esta aplicación en el catálogo del portal
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $portalUrl,
        private string $application,
    ) {
    }

    public function access(string $token): PortalAccess
    {
        $data = $this->get('/api/acceso', ['aplicacion' => $this->application], $token);

        return new PortalAccess(
            (string) ($data['email'] ?? ''),
            (string) ($data['name'] ?? ''),
            self::strings($data['roles'] ?? []),
            self::strings($data['notificationEmails'] ?? []),
            self::applications($data['applications'] ?? []),
        );
    }

    /** @return list<ReachableApplication> */
    private static function applications(mixed $values): array
    {
        $applications = [];
        foreach (is_array($values) ? $values : [] as $application) {
            if (is_array($application)) {
                $applications[] = new ReachableApplication(
                    (string) ($application['code'] ?? ''),
                    (string) ($application['name'] ?? ''),
                    (string) ($application['url'] ?? ''),
                );
            }
        }

        return $applications;
    }

    public function recipients(string $token, string $role): array
    {
        $recipients = [];
        foreach ($this->get('/api/avisos', ['aplicacion' => $this->application, 'rol' => $role], $token) as $recipient) {
            if (is_array($recipient)) {
                $recipients[] = new Recipient((string) ($recipient['name'] ?? ''), self::strings($recipient['emails'] ?? []));
            }
        }

        return $recipients;
    }

    public function members(string $token): array
    {
        $members = [];
        foreach ($this->get('/api/personas', ['aplicacion' => $this->application], $token) as $member) {
            if (is_array($member)) {
                $members[] = new Member(
                    (string) ($member['email'] ?? ''),
                    (string) ($member['name'] ?? ''),
                    self::strings($member['roles'] ?? []),
                    self::strings($member['notificationEmails'] ?? []),
                );
            }
        }

        return $members;
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<mixed>
     */
    private function get(string $path, array $query, string $token): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->portalUrl, '/').$path, [
                'query' => $query,
                'auth_bearer' => $token,
                'timeout' => self::TIMEOUT,
            ]);

            $status = $response->getStatusCode();

            // 401: la sesión no vale. 403: no tiene acceso a esta aplicación
            // (lo dan /api/avisos y /api/personas). Para quien pregunta, las
            // dos son "no".
            if (401 === $status || 403 === $status) {
                throw new SessionRejected('El portal no acepta la sesión.');
            }

            if (200 !== $status) {
                throw new PortalUnavailable(sprintf('El portal ha contestado %d a %s.', $status, $path));
            }

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new PortalUnavailable(sprintf('No se ha podido preguntar al portal (%s).', $e->getMessage()), 0, $e);
        }
    }

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_map('strval', array_filter($values, 'is_string'))) : [];
    }
}
