<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Security;

use Ampa\PortalCliente\Portal\PortalAccess;

/**
 * Traduce lo que dice el portal a los roles de Symfony de ESTA aplicación.
 *
 * Por defecto (PortalRolesAsSymfonyRoles) cada rol del portal es un rol de
 * Symfony con el mismo nombre: "usuario" → ROLE_USUARIO. Una aplicación que
 * necesite más lo sustituye con el suyo en services.yaml. Fichajes, por
 * ejemplo: ROLE_EMPLOYEE solo si además del permiso "empleado" tiene un
 * contrato en vigor, que es cosa suya y el portal no sabe.
 *
 * Sin ningún rol, no entra: la aplicación responde 403 "no tienes acceso".
 */
interface ApplicationRoles
{
    /** @return list<string> roles de Symfony (ROLE_…); vacía = no entra */
    public function rolesFor(PortalAccess $access): array;
}
