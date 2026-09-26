<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use App\Portal\Domain\User\User;

/**
 * Quién llama a una ruta de servidor a servidor (/api/avisos, /api/personas):
 * una persona, con el token de su sesión que reenvía la aplicación, o el
 * servidor de una aplicación de la suite sin nadie detrás, con el token de su
 * cuenta de servicio (el resumen diario de tareas, a las 5:00).
 */
final readonly class Caller
{
    private function __construct(
        private ?User $user,
        private ?string $serviceAccount,
    ) {
    }

    public static function person(User $user): self
    {
        return new self($user, null);
    }

    public static function application(string $serviceAccount): self
    {
        return new self(null, $serviceAccount);
    }

    /**
     * Si puede preguntar por esa aplicación. Una persona, solo si tiene algún
     * rol en ella: con una sesión de listados no se sacan los correos de
     * fichajes. Una aplicación, siempre: todas corren con la misma cuenta de
     * servicio y el portal no puede distinguir cuál llama; son todas de la
     * suite y es la misma información que ya tienen sus administradores.
     */
    public function mayAskAbout(string $application): bool
    {
        return null === $this->user || [] !== $this->user->rolesIn($application);
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getServiceAccount(): ?string
    {
        return $this->serviceAccount;
    }
}
