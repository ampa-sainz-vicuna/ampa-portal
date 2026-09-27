<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Portal\Application\Heartbeat\BackupLauncher;
use App\Portal\Application\Heartbeat\HeartbeatFailed;

/** El job de las copias, en los tests: cuenta cuántas veces se lanza. */
final class FakeBackupLauncher implements BackupLauncher
{
    private bool $configured = false;
    private bool $failing = false;
    private int $launches = 0;

    public function configured(): void
    {
        $this->configured = true;
    }

    public function failing(): void
    {
        $this->configured = true;
        $this->failing = true;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function launch(): string
    {
        if ($this->failing) {
            throw new HeartbeatFailed('Cloud Run ha contestado 403: sin permiso.');
        }

        ++$this->launches;

        return 'ampa-copias-'.$this->launches;
    }

    public function getLaunches(): int
    {
        return $this->launches;
    }
}
