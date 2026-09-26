<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * Quién hay en esta aplicación, para usar desde un caso de uso o un
 * controlador:
 *
 *   $members->all()  // las personas activas con algún rol aquí, por nombre
 *
 * Por ejemplo, tareas lo usa para elegir a quién se asigna una tarea. Como
 * SuiteRecipients, pregunta al portal con el token de quien hace la petición
 * en curso; sin nadie detrás (una tarea programada), desde la 0.1.3, con el de
 * este servidor (CallerToken).
 */
final readonly class SuiteMembers
{
    public function __construct(
        private Portal $portal,
        private CallerToken $token,
    ) {
    }

    /**
     * @return list<Member>
     *
     * @throws SessionRejected   si el portal no acepta el token
     * @throws PortalUnavailable si el portal no contesta, o no hay sesión y
     *                           este servidor no tiene token (en desarrollo)
     */
    public function all(): array
    {
        return $this->portal->members($this->token->current());
    }

    /** Si ese correo (el de la cuenta, sin mirar mayúsculas) es de alguien de aquí. */
    public function has(string $email): bool
    {
        $email = mb_strtolower(trim($email));

        foreach ($this->all() as $member) {
            if (mb_strtolower($member->getEmail()) === $email) {
                return true;
            }
        }

        return false;
    }
}
