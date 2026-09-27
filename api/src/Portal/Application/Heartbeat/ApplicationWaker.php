<?php

declare(strict_types=1);

namespace App\Portal\Application\Heartbeat;

use App\Portal\Domain\Suite\Application;

/**
 * Despierta a una aplicación para que haga su trabajo programado: en
 * producción, POST {url}/api/latido con el token de la cuenta de servicio de
 * la suite (HttpApplicationWaker; lo recibe el cliente 0.1.5).
 */
interface ApplicationWaker
{
    /**
     * @throws HeartbeatFailed si no contesta, o contesta con un error
     */
    public function wake(Application $application): void;
}
