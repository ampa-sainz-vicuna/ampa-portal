<?php

declare(strict_types=1);

namespace App\Portal\Application\Heartbeat;

/**
 * Qué ha hecho el latido, paso a paso: es la respuesta de POST /api/latido
 * (la que se ve en la consola de Cloud Scheduler) y la salida de
 * `app:latido`.
 */
final class HeartbeatReport
{
    public const string DONE = 'hecho';
    public const string SKIPPED = 'saltado';
    public const string FAILED = 'fallo';

    /** @var list<array{step: string, outcome: string, detail: string}> */
    private array $steps = [];

    public function done(string $step, string $detail): void
    {
        $this->add($step, self::DONE, $detail);
    }

    public function skipped(string $step, string $detail): void
    {
        $this->add($step, self::SKIPPED, $detail);
    }

    public function failed(string $step, string $detail): void
    {
        $this->add($step, self::FAILED, $detail);
    }

    public function hasFailures(): bool
    {
        return in_array(self::FAILED, array_column($this->steps, 'outcome'), true);
    }

    /** @return list<array{step: string, outcome: string, detail: string}> */
    public function getSteps(): array
    {
        return $this->steps;
    }

    private function add(string $step, string $outcome, string $detail): void
    {
        $this->steps[] = ['step' => $step, 'outcome' => $outcome, 'detail' => $detail];
    }
}
