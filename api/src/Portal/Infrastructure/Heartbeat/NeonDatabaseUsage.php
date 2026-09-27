<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Heartbeat;

use App\Portal\Application\Heartbeat\ComputeUsage;
use App\Portal\Application\Heartbeat\DatabaseUsage;
use App\Portal\Application\Heartbeat\HeartbeatFailed;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * El cómputo gastado por el proyecto `ampa` de Neon, con su API:
 * GET /api/v2/projects/{id}, campo `compute_time_seconds` (segundos de CPU
 * en el periodo en curso: 0,25 CU encendida una hora son 900, un cuarto de
 * CU-hora) y `consumption_period_end`.
 *
 * La API de consumo "de verdad" de Neon (consumption_history) es solo de los
 * planes de pago; este campo del proyecto es lo que hay en el gratuito.
 * **Contrastarlo con la consola de Neon la primera vez** (Projects → la
 * columna de cómputo): si no cuadra, el aviso sale antes o después de tiempo.
 *
 * Necesita una clave de la API de Neon (secreto `neon-api-key`; mejor una
 * de proyecto, que solo ve `ampa`) y el ID del proyecto. Sin las dos, el
 * latido se salta este paso.
 */
final readonly class NeonDatabaseUsage implements DatabaseUsage
{
    private const string API = 'https://console.neon.tech/api/v2/projects/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $projectId,
        private string $apiKey,
        private float $hoursAllowed,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->projectId) && '' !== trim($this->apiKey);
    }

    public function current(): ComputeUsage
    {
        try {
            $response = $this->httpClient->request('GET', self::API.rawurlencode(trim($this->projectId)), [
                'headers' => ['Authorization' => 'Bearer '.trim($this->apiKey), 'Accept' => 'application/json'],
                'timeout' => 15,
            ]);
            $status = $response->getStatusCode();
            $body = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new HeartbeatFailed(sprintf('Neon no contesta: %s', $e->getMessage()), 0, $e);
        }

        if (200 !== $status) {
            throw new HeartbeatFailed(sprintf('Neon ha contestado %d: %s', $status, (string) ($body['message'] ?? 'sin detalle')));
        }

        $project = $body['project'] ?? null;
        $seconds = is_array($project) ? ($project['compute_time_seconds'] ?? null) : null;

        if (!is_int($seconds) && !is_float($seconds)) {
            throw new HeartbeatFailed('La respuesta de Neon no trae compute_time_seconds.');
        }

        $periodEnd = null;
        if (is_string($project['consumption_period_end'] ?? null)) {
            try {
                $periodEnd = new \DateTimeImmutable($project['consumption_period_end']);
            } catch (\Exception) {
                // Sin fecha el aviso sigue valiendo.
            }
        }

        return new ComputeUsage($seconds / 3600, $this->hoursAllowed, $periodEnd);
    }
}
