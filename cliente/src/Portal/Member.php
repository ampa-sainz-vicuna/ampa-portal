<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * Alguien con algún rol en esta aplicación: el correo de su CUENTA (el que la
 * identifica en toda la suite, el mismo que getUserIdentifier()), su nombre y
 * sus roles aquí.
 *
 * No confundir con Recipient: aquel lleva los correos a los que avisar, que
 * pueden ser el personal. Para guardar "quién" en una aplicación, este.
 */
final readonly class Member
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $email,
        private string $name,
        private array $roles,
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
}
