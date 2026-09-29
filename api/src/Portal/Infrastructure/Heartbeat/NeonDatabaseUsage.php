<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Heartbeat;

use App\Portal\Application\Heartbeat\ComputeUsage;
use App\Portal\Application\Heartbeat\DatabaseUsage;
use App\Portal\Application\Heartbeat\HeartbeatFailed;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * El cómputo gastado por el proyecto `ampa` de Neon, con su API, como un
 * **máximo**: horas encendida × el tamaño más grande al que puede crecer.
 *
 * Neon cobra la CU **asignada** × el tiempo encendida, y la base escala sola
 * (0,25 a 2 CU). El número exacto solo lo da la API de consumo
 * (`consumption_history`), que no existe en el plan gratuito. Lo que sí hay:
 *
 * - GET /projects/{id}: `active_time_seconds` (encendida en el periodo) y
 *   `consumption_period_end`. Su `compute_time_seconds` **no sirve**: es la
 *   CPU usada de verdad (vale lo mismo que `cpu_used_sec`), muy por debajo
 *   de lo que se cobra. Comprobado el 29/09/2026: 1,6 CU-horas según ese
 *   campo, 9,94 según la consola, 5,9 horas encendida.
 * - GET /projects/{id}/endpoints: `autoscaling_limit_max_cu` de cada
 *   máquina.
 *
 * Con las dos, el máximo posible (11,8 ese día). El aviso del 80 % sale así
 * antes de tiempo, nunca tarde. Si alguna vez tarda en llegar al aviso,
 * mirar la consola (Billing o el proyecto → Usage), que da la cifra exacta.
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
        $project = $this->get('')['project'] ?? null;
        $activeSeconds = is_array($project) ? ($project['active_time_seconds'] ?? null) : null;

        if (!is_int($activeSeconds) && !is_float($activeSeconds)) {
            throw new HeartbeatFailed('La respuesta de Neon no trae active_time_seconds.');
        }

        $largestSize = $this->largestComputeSize();

        $periodEnd = null;
        if (is_string($project['consumption_period_end'] ?? null)) {
            try {
                $periodEnd = new \DateTimeImmutable($project['consumption_period_end']);
            } catch (\Exception) {
                // Sin fecha el aviso sigue valiendo.
            }
        }

        return new ComputeUsage($activeSeconds * $largestSize / 3600, $this->hoursAllowed, $periodEnd, upperBound: true);
    }

    /** La CU más grande a la que puede crecer cualquier máquina del proyecto. */
    private function largestComputeSize(): float
    {
        $endpoints = $this->get('/endpoints')['endpoints'] ?? null;
        $sizes = [];

        foreach (is_array($endpoints) ? $endpoints : [] as $endpoint) {
            $size = is_array($endpoint) ? ($endpoint['autoscaling_limit_max_cu'] ?? null) : null;
            if ((is_int($size) || is_float($size)) && $size > 0) {
                $sizes[] = (float) $size;
            }
        }

        if ([] === $sizes) {
            throw new HeartbeatFailed('La respuesta de Neon no trae el tamaño de ninguna máquina (autoscaling_limit_max_cu).');
        }

        return max($sizes);
    }

    /**
     * @return array<mixed>
     */
    private function get(string $path): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API.rawurlencode(trim($this->projectId)).$path, [
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

        return $body;
    }
}
