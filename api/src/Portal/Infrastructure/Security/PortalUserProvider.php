<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Traduce el correo del token en quien ha entrado, mirando la base de datos
 * en CADA petición: desactivar a alguien o quitarle la gestión de permisos
 * surte efecto al momento, sin esperar a que caduque su sesión.
 *
 * @implements UserProviderInterface<PortalUser>
 */
final readonly class PortalUserProvider implements UserProviderInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    public function loadUserByIdentifier(string $identifier): PortalUser
    {
        try {
            $user = $this->users->findByEmail(EmailAddress::fromString($identifier));
        } catch (\InvalidArgumentException) {
            $user = null;
        }

        if (null === $user || !$user->isActive()) {
            throw new UserNotFoundException(sprintf('"%s" no está dado de alta o está desactivado.', $identifier));
        }

        $roles = [PortalUser::ROLE_USER];
        if ($user->isSuiteAdmin()) {
            $roles[] = PortalUser::ROLE_SUITE_ADMIN;
        }

        return new PortalUser($user->getEmail()->getValue(), $user->getName(), $roles);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof PortalUser) {
            throw new UnsupportedUserException(sprintf('Usuario no soportado: "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return PortalUser::class === $class;
    }
}
