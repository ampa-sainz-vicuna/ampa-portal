<?php

declare(strict_types=1);

namespace App\Portal\Application\Heartbeat;

/** Un paso del latido que no ha salido: se registra y se sigue con el resto. */
final class HeartbeatFailed extends \RuntimeException
{
}
