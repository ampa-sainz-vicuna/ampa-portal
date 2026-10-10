<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Heartbeat;

use App\Portal\Application\Heartbeat\ApplicationWaker;
use App\Portal\Application\Heartbeat\HeartbeatFailed;
use App\Portal\Domain\Suite\Application;
use App\Portal\Infrastructure\Google\MetadataServer;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * POST {raíz de la aplicación}/api/latido, con el token de identidad de la
 * cuenta de servicio de la suite emitido para ESA raíz (la misma que tiene la
 * aplicación en su configuración del cliente como `latido_audiencia`). La
 * ruta la pone el cliente 0.1.5.
 *
 * La raíz es el esquema y el dominio de la dirección del catálogo, sin ruta:
 * la de familias es `…/junta` (su tarjeta lleva a la parte de la junta; en la
 * raíz están las familias), pero su API está en la raíz del dominio, como en
 * todas.
 *
 * Espera hasta un minuto por aplicación: puede estar dormida (unos segundos
 * en despertar) y su trabajo programado tarda algo (fichajes cierra días y
 * manda correos). Van una detrás de otra y Cloud Run corta el latido a los 5
 * minutos (--timeout de deploy/desplegar.sh): con más de cuatro aplicaciones
 * lentas a la vez habría que repensarlo.
 */
final readonly class HttpApplicationWaker implements ApplicationWaker
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private MetadataServer $metadata,
    ) {
    }

    public function wake(Application $application): void
    {
        $url = self::root($application->getUrl());
        $token = $this->metadata->identityToken($url);

        try {
            $response = $this->httpClient->request('POST', $url.'/api/latido', [
                'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'],
                'timeout' => 60,
                'max_duration' => 60,
            ]);
            $status = $response->getStatusCode();
        } catch (ExceptionInterface $e) {
            throw new HeartbeatFailed(sprintf('no contesta: %s', $e->getMessage()), 0, $e);
        }

        if ($status < 200 || $status >= 300) {
            throw new HeartbeatFailed(sprintf('ha contestado %d: %s', $status, mb_substr($response->getContent(false), 0, 300)));
        }
    }

    /** «https://familias.ejemplo/junta» → «https://familias.ejemplo» (con el puerto, si lo lleva). */
    private static function root(string $url): string
    {
        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return rtrim($url, '/');
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
