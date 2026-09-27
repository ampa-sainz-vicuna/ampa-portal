<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Heartbeat;

/** Una llamada a /api/latido que no viene de la suite (o no está configurado). */
final class InvalidHeartbeat extends \RuntimeException
{
}
