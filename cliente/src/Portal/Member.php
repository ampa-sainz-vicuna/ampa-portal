<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * Alguien con algún rol en esta aplicación: el correo de su CUENTA (el que la
 * identifica en toda la suite, el mismo que getUserIdentifier()), su nombre,
 * sus roles aquí y, desde la 0.1.2, a dónde quiere los avisos.
 *
 * Para guardar "quién" en una aplicación, getEmail(). Para escribirle,
 * getNotificationEmails(): puede ser el personal, o los dos. Recipient lleva
 * lo mismo pero por rol y sin decir de quién es cada correo; este sirve para
 * avisar a una persona concreta ("te han asignado una tarea").
 */
final readonly class Member
{
    /** @var list<string> */
    private array $notificationEmails;

    /**
     * @param list<string> $roles
     * @param list<string> $notificationEmails vacío = el de la cuenta (un portal
     *                                         anterior a la 0.1.2 no los manda)
     */
    public function __construct(
        private string $email,
        private string $name,
        private array $roles,
        array $notificationEmails = [],
    ) {
        $this->notificationEmails = [] === $notificationEmails ? [$email] : $notificationEmails;
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

    /** @return list<string> a dónde avisarle, nunca vacío */
    public function getNotificationEmails(): array
    {
        return $this->notificationEmails;
    }
}
