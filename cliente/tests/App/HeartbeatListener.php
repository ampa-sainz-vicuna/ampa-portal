<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Tests\App;

use Ampa\PortalCliente\Event\HeartbeatReceived;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Cuenta los latidos que llegan, para comprobar que el evento llega a los
 * oyentes de la aplicación (en fichajes, el trabajo programado). Con
 * `$failing`, falla como fallaría un trabajo roto.
 */
#[AsEventListener]
final class HeartbeatListener
{
    public static int $beats = 0;
    public static bool $failing = false;

    public function __invoke(HeartbeatReceived $event): void
    {
        if (self::$failing) {
            throw new \RuntimeException('El cierre del día ha fallado.');
        }

        ++self::$beats;
    }
}
