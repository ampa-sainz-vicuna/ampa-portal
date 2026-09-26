<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Testing;

use Ampa\PortalCliente\Portal\ApplicationIdentity;

/**
 * El token de este servidor, en los tests: uno fijo que FakePortal acepta en
 * `recipients()` y `members()` (no en `access()`: no es una persona). Se
 * activa en el `when@test` de services.yaml de la aplicación:
 *
 *   Ampa\PortalCliente\Portal\ApplicationIdentity:
 *       class: Ampa\PortalCliente\Testing\FakeApplicationIdentity
 */
final class FakeApplicationIdentity implements ApplicationIdentity
{
    public const string TOKEN = 'fake-portal-aplicacion';

    public function token(): string
    {
        return self::TOKEN;
    }
}
