<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Heartbeat;

use App\Portal\Application\Heartbeat\HeartbeatFailed;
use App\Portal\Application\User\GrantsChangeNotifier;
use App\Portal\Domain\Suite\Application;
use App\Portal\Infrastructure\Google\MetadataServer;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * POST {raíz de la aplicación}/api/service/permisos con
 * `{"aplicacion": "<código>"}`, con el mismo token de identidad que el latido
 * (ver HttpApplicationWaker): la aplicación vuelve a leer del portal quién
 * tiene qué.
 *
 * Nunca lanza. Guardar la ficha ya está hecho: si el aviso falla se apunta y
 * el latido diario de la aplicación lo corrige.
 */
final readonly class HttpGrantsChangeNotifier implements GrantsChangeNotifier
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private MetadataServer $metadata,
        private LoggerInterface $logger,
    ) {
    }

    public function notify(Application $application): void
    {
        $code = $application->getCode();
        $url = HttpApplicationWaker::root($application->getUrl());

        try {
            $token = $this->metadata->identityToken($url);
        } catch (HeartbeatFailed $e) {
            // Fuera de Cloud Run no hay servidor de metadatos: en local es lo normal.
            $this->logger->warning('No se avisa a {application} del cambio de permisos (sin token de identidad): {reason}', [
                'application' => $code,
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        try {
            $response = $this->httpClient->request('POST', $url.'/api/service/permisos', [
                'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'],
                'json' => ['aplicacion' => $code],
                // Corto: la pantalla de permisos espera. Si Documentos tarda más, sigue
                // sincronizando igual y aquí queda un error; el latido diario lo cubre.
                'timeout' => 10,
                'max_duration' => 30,
            ]);
            $status = $response->getStatusCode();

            if ($status < 200 || $status >= 300) {
                $this->logger->error('{application} ha contestado {status} al aviso de cambio de permisos: {body}', [
                    'application' => $code,
                    'status' => $status,
                    'body' => mb_substr($response->getContent(false), 0, 300),
                ]);
            }
        } catch (ExceptionInterface $e) {
            $this->logger->error('No se ha podido avisar a {application} del cambio de permisos: {reason}', [
                'application' => $code,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
