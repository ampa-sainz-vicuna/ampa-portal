<?php

declare(strict_types=1);

namespace App\Portal\Domain\User;

use App\Portal\Domain\Suite\ApplicationCatalog;

/**
 * Una persona de la suite y sus permisos. Raíz de agregado.
 *
 * Nunca se borra: se desactiva. Desactivada, no entra en ninguna aplicación
 * (el portal lo comprueba en cada petición de cada una), pero sigue ahí con
 * sus permisos por si vuelve, y las aplicaciones siguen pudiendo decir quién
 * hizo algo con su correo.
 *
 * El nombre lo pone quien da el alta, no Google: es el que se ve en la
 * pantalla de permisos y en la barra de cada aplicación, y así no depende de
 * cómo tenga cada uno puesto su perfil de Google.
 *
 * Dos correos: el principal es la cuenta de Google con la que entra (y no
 * cambia nunca: es su identidad); el segundo, opcional, es donde además (o en
 * vez de ahí) quiere recibir los avisos. Ver NotificationTarget.
 */
final class User
{
    private function __construct(
        private readonly UserId $id,
        private readonly EmailAddress $email,
        private string $name,
        private bool $active,
        private Grants $grants,
        private ?EmailAddress $secondaryEmail,
        private NotificationTarget $notify,
    ) {
    }

    public static function register(UserId $id, EmailAddress $email, string $name, Grants $grants): self
    {
        return new self($id, $email, self::validateName($name), true, $grants, null, NotificationTarget::Primary);
    }

    public function rename(string $name): void
    {
        $this->name = self::validateName($name);
    }

    public function changeGrants(Grants $grants): void
    {
        $this->grants = $grants;
    }

    /**
     * El segundo correo y a cuál van los avisos, juntos: lo uno depende de lo
     * otro (sin segundo correo no se le puede mandar nada ahí).
     *
     * @throws \InvalidArgumentException si pide avisos en un segundo correo que no tiene
     */
    public function changeContact(?EmailAddress $secondaryEmail, NotificationTarget $notify): void
    {
        // Un segundo correo igual al principal no aporta nada y haría llegar
        // cada aviso dos veces.
        if (null !== $secondaryEmail && $secondaryEmail->equals($this->email)) {
            $secondaryEmail = null;
        }

        if (null === $secondaryEmail && NotificationTarget::Primary !== $notify) {
            throw new \InvalidArgumentException('Para recibir los avisos en el segundo correo, primero hay que indicarlo.');
        }

        $this->secondaryEmail = $secondaryEmail;
        $this->notify = $notify;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function reactivate(): void
    {
        $this->active = true;
    }

    /**
     * Si puede entrar en el portal: activa y con algo que hacer en la suite.
     * Sin ningún permiso, el portal le enseñaría una pantalla vacía.
     */
    public function canSignIn(): bool
    {
        return $this->active && !$this->grants->isEmpty();
    }

    /** Quien gestiona los permisos de toda la suite. */
    public function isSuiteAdmin(): bool
    {
        return $this->active && $this->grants->has(ApplicationCatalog::PORTAL, ApplicationCatalog::SUITE_ADMIN_ROLE);
    }

    /** @return list<string> vacía si está desactivada o no entra en esa aplicación */
    public function rolesIn(string $application): array
    {
        return $this->active ? $this->grants->rolesIn($application) : [];
    }

    /**
     * A dónde mandarle un aviso, según lo que haya elegido.
     *
     * @return list<EmailAddress>
     */
    public function getNotificationEmails(): array
    {
        return match (true) {
            null === $this->secondaryEmail, NotificationTarget::Primary === $this->notify => [$this->email],
            NotificationTarget::Secondary === $this->notify => [$this->secondaryEmail],
            default => [$this->email, $this->secondaryEmail],
        };
    }

    public function getId(): UserId
    {
        return $this->id;
    }

    public function getEmail(): EmailAddress
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getGrants(): Grants
    {
        return $this->grants;
    }

    public function getSecondaryEmail(): ?EmailAddress
    {
        return $this->secondaryEmail;
    }

    public function getNotify(): NotificationTarget
    {
        return $this->notify;
    }

    private static function validateName(string $name): string
    {
        $trimmed = trim($name);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('El nombre no puede estar vacío.');
        }

        return $trimmed;
    }
}
