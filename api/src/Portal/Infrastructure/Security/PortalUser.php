<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Quien ha entrado en el portal, visto por el sistema de seguridad de Symfony.
 *
 * Existe para que User (el dominio) no tenga que implementar UserInterface: el
 * dominio no debe saber que hay un framework de seguridad detrás.
 */
final readonly class PortalUser implements UserInterface
{
    public const string ROLE_USER = 'ROLE_USER';
    public const string ROLE_SUITE_ADMIN = 'ROLE_SUITE_ADMIN';

    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $email,
        private string $name,
        private array $roles,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * No se guardan contraseñas: la identidad la acredita Google. Queda vacío
     * porque la interfaz todavía lo exige, aunque esté marcado como obsoleto.
     */
    public function eraseCredentials(): void
    {
    }
}
