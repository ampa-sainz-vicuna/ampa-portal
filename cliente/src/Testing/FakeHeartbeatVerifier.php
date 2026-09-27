<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Testing;

use Ampa\PortalCliente\Heartbeat\HeartbeatVerifier;
use Ampa\PortalCliente\Heartbeat\InvalidHeartbeat;

/**
 * El latido, en los tests de la aplicación: vale el token
 * FakeHeartbeatVerifier::TOKEN y ninguno más. Se activa en el `when@test`
 * de services.yaml:
 *
 *   Ampa\PortalCliente\Heartbeat\HeartbeatVerifier:
 *       class: Ampa\PortalCliente\Testing\FakeHeartbeatVerifier
 *
 * y el test hace `POST /api/latido` con `Authorization: Bearer ` y el token.
 */
final class FakeHeartbeatVerifier implements HeartbeatVerifier
{
    public const string TOKEN = 'fake-portal-latido';

    public function verify(string $token): void
    {
        if (self::TOKEN !== $token) {
            throw new InvalidHeartbeat('No es el token del latido de los tests.');
        }
    }
}
