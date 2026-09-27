<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Google;

use App\Portal\Application\Heartbeat\HeartbeatFailed;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Los tokens de la cuenta de servicio con la que corre el portal en Cloud
 * Run, sin claves que guardar: los firma el "servidor de metadatos" de
 * Google, una dirección interna que solo existe dentro de Google Cloud (el
 * mismo mecanismo que usa el cliente, MetadataServerIdentity, y fichajes para
 * Drive).
 *
 * - `identityToken($audience)`: "soy esta cuenta de servicio" para UNA
 *   dirección. Con él el portal llama a /api/latido de cada aplicación.
 * - `accessToken()`: para las API de Google (aquí, lanzar el job de las
 *   copias en Cloud Run).
 *
 * Fuera de Google Cloud la dirección no existe: HeartbeatFailed.
 */
final class MetadataServer
{
    private const string BASE = 'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @throws HeartbeatFailed
     */
    public function identityToken(string $audience): string
    {
        // "full" añade el correo de la cuenta al token: quien lo recibe lo
        // mira para saber que es de la suite.
        return $this->fetch('identity', ['audience' => $audience, 'format' => 'full']);
    }

    /**
     * @throws HeartbeatFailed
     */
    public function accessToken(): string
    {
        $body = json_decode($this->fetch('token', []), true);

        if (!is_array($body) || !is_string($body['access_token'] ?? null) || '' === $body['access_token']) {
            throw new HeartbeatFailed('El servidor de metadatos no ha devuelto un token de acceso.');
        }

        return $body['access_token'];
    }

    /**
     * @param array<string, string> $query
     *
     * @throws HeartbeatFailed
     */
    private function fetch(string $path, array $query): string
    {
        try {
            $content = trim($this->httpClient->request('GET', self::BASE.$path, [
                'headers' => ['Metadata-Flavor' => 'Google'],
                'query' => $query,
                'timeout' => 5,
            ])->getContent());
        } catch (ExceptionInterface $e) {
            throw new HeartbeatFailed('No se ha podido pedir un token al servidor de metadatos (solo existe dentro de Google Cloud).', 0, $e);
        }

        if ('' === $content) {
            throw new HeartbeatFailed('El servidor de metadatos no ha devuelto nada.');
        }

        return $content;
    }
}
