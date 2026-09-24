<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Un proveedor de usuarios que no carga a nadie.
 *
 * Symfony exige que el cortafuegos tenga uno, pero aquí no hace falta: el
 * usuario lo construye PortalAuthenticator con lo que contesta el portal, y
 * el cortafuegos es sin estado (no hay que "refrescar" a nadie entre
 * peticiones). Si algo intenta usarlo, es un error de configuración.
 *
 * @implements UserProviderInterface<PortalUser>
 */
final class PortalUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException('Los usuarios salen del portal, no de un proveedor: usa PortalAuthenticator.');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new UnsupportedUserException('El cortafuegos del portal es sin estado: no se refresca a nadie.');
    }

    public function supportsClass(string $class): bool
    {
        return PortalUser::class === $class;
    }
}
