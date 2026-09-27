<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Portal\Application\Heartbeat\ApplicationWaker;
use App\Portal\Application\Heartbeat\HeartbeatFailed;
use App\Portal\Domain\Suite\Application;

/** Las aplicaciones, en los tests: apunta a quién se ha despertado. */
final class FakeApplicationWaker implements ApplicationWaker
{
    /** @var list<string> */
    private array $woken = [];

    /** @var list<string> */
    private array $failing = [];

    public function fails(string $code): void
    {
        $this->failing[] = $code;
    }

    public function wake(Application $application): void
    {
        if (in_array($application->getCode(), $this->failing, true)) {
            throw new HeartbeatFailed('ha contestado 500: se ha roto');
        }

        $this->woken[] = $application->getCode();
    }

    /** @return list<string> */
    public function getWoken(): array
    {
        return $this->woken;
    }
}
