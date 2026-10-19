<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Portal\Application\User\GrantsChangeNotifier;
use App\Portal\Domain\Suite\Application;

/** Las aplicaciones, en los tests: apunta a cuáles se ha avisado de un cambio de permisos. */
final class RecordingGrantsChangeNotifier implements GrantsChangeNotifier
{
    /** @var list<string> */
    private array $notified = [];

    public function notify(Application $application): void
    {
        $this->notified[] = $application->getCode();
    }

    /** @return list<string> */
    public function getNotified(): array
    {
        return $this->notified;
    }
}
