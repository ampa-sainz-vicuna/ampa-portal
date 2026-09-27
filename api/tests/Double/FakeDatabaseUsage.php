<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Portal\Application\Heartbeat\ComputeUsage;
use App\Portal\Application\Heartbeat\DatabaseUsage;
use App\Portal\Application\Heartbeat\HeartbeatFailed;

/** Neon, en los tests: sin configurar hasta que se le dice cuánto va gastado. */
final class FakeDatabaseUsage implements DatabaseUsage
{
    private ?ComputeUsage $usage = null;
    private bool $down = false;

    public function uses(float $hours, float $allowed = 100): void
    {
        $this->usage = new ComputeUsage($hours, $allowed);
    }

    public function isDown(): void
    {
        $this->down = true;
    }

    public function isConfigured(): bool
    {
        return null !== $this->usage || $this->down;
    }

    public function current(): ComputeUsage
    {
        if ($this->down || null === $this->usage) {
            throw new HeartbeatFailed('Neon no contesta.');
        }

        return $this->usage;
    }
}
