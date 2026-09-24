<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Security;

use Ampa\PortalCliente\Portal\PortalAccess;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Quien hace la petición, visto por el sistema de seguridad de Symfony de la
 * aplicación.
 *
 * Se identifica por el correo (`getUserIdentifier()`), como hacían fichajes y
 * listados: es lo que las aplicaciones guardan para saber quién hizo algo.
 */
final readonly class PortalUser implements UserInterface
{
    /**
     * @param list<string> $roles los de Symfony (ROLE_…), ya traducidos por ApplicationRoles
     */
    public function __construct(
        private PortalAccess $access,
        private array $roles,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->access->getEmail();
    }

    public function getName(): string
    {
        return $this->access->getName();
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /** @return list<string> los del portal en esta aplicación ("admin", "usuario"…) */
    public function getPortalRoles(): array
    {
        return $this->access->getRoles();
    }

    /** @return list<string> a dónde quiere que le lleguen los avisos */
    public function getNotificationEmails(): array
    {
        return $this->access->getNotificationEmails();
    }

    /**
     * No se guardan contraseñas: la identidad la acredita Google, en el
     * portal. Queda vacío porque la interfaz todavía lo exige.
     */
    public function eraseCredentials(): void
    {
    }
}
