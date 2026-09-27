<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Heartbeat;

use App\Portal\Application\Heartbeat\BackupLauncher;
use App\Portal\Application\Heartbeat\HeartbeatFailed;
use App\Portal\Infrastructure\Google\MetadataServer;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Lanza el job de Cloud Run de las copias (deploy/copias/) con la API de
 * Cloud Run. Es lo mismo que `gcloud run jobs execute ampa-copias`.
 *
 * El job, y no el portal, es quien tiene las contraseñas de todas las bases:
 * el portal solo puede decirle "arranca". Para eso su cuenta de servicio
 * necesita el rol Invocador de Cloud Run SOBRE ESE JOB (lo da
 * deploy/copias/preparar.sh).
 *
 * COPIAS_JOB, el nombre completo del job:
 * projects/ID/locations/europe-west1/jobs/ampa-copias. Vacío (desarrollo),
 * no se lanza nada.
 */
final readonly class CloudRunBackupLauncher implements BackupLauncher
{
    private const string API = 'https://run.googleapis.com/v2/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private MetadataServer $metadata,
        private string $job,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->job);
    }

    public function launch(): string
    {
        $token = $this->metadata->accessToken();

        try {
            $response = $this->httpClient->request('POST', self::API.trim($this->job, '/ ').':run', [
                'headers' => ['Authorization' => 'Bearer '.$token],
                'json' => new \stdClass(),
                'timeout' => 30,
            ]);
            $status = $response->getStatusCode();
            $body = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new HeartbeatFailed(sprintf('Cloud Run no contesta: %s', $e->getMessage()), 0, $e);
        }

        if ($status < 200 || $status >= 300) {
            $message = is_array($body['error'] ?? null) ? (string) ($body['error']['message'] ?? '') : '';

            throw new HeartbeatFailed(sprintf('Cloud Run ha contestado %d: %s', $status, '' !== $message ? $message : 'sin detalle'));
        }

        // La respuesta es una operación de larga duración; en sus metadatos
        // va el nombre de la ejecución (ampa-copias-abc12).
        $execution = $body['metadata']['name'] ?? $body['name'] ?? '';

        return is_string($execution) && '' !== $execution ? basename($execution) : 'sin nombre';
    }
}
