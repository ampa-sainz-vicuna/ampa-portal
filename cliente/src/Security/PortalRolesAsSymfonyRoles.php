<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Security;

use Ampa\PortalCliente\Portal\PortalAccess;

/**
 * Lo normal: "usuario" → ROLE_USUARIO, "admin" → ROLE_ADMIN.
 */
final class PortalRolesAsSymfonyRoles implements ApplicationRoles
{
    public function rolesFor(PortalAccess $access): array
    {
        return array_map(
            static fn (string $role): string => 'ROLE_'.strtoupper(str_replace('-', '_', $role)),
            $access->getRoles(),
        );
    }
}
