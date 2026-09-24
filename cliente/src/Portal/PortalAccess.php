<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * Lo que contesta el portal a "¿quién es este token y qué puede hacer aquí?"
 * (`GET /api/acceso`).
 */
final readonly class PortalAccess
{
    /**
     * @param list<string> $roles              los roles del portal en ESTA aplicación ("admin", "usuario"…); vacía si no tiene ninguno
     * @param list<string> $notificationEmails a dónde quiere que le lleguen los avisos
     */
    public function __construct(
        private string $email,
        private string $name,
        private array $roles,
        private array $notificationEmails,
    ) {
    }

    public function getEmail(): string
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

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /** @return list<string> */
    public function getNotificationEmails(): array
    {
        return $this->notificationEmails;
    }
}
