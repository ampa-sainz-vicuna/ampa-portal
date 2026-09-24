<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Tests\App;

use Ampa\PortalCliente\Event\ApplicationOpened;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Apunta quién ha abierto la aplicación, para comprobar que el evento llega
 * a los oyentes de la aplicación (en fichajes, el "cron").
 */
#[AsEventListener]
final class OpenedListener
{
    /** @var list<string> */
    public static array $opened = [];

    public function __invoke(ApplicationOpened $event): void
    {
        self::$opened[] = $event->getUser()->getUserIdentifier();
    }
}
