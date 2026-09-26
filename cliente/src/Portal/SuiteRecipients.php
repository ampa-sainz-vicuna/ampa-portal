<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * A quién avisar en esta aplicación, para usar desde un caso de uso:
 *
 *   $recipients->withRole('admin')  // los administradores, cada uno en el correo que eligió
 *
 * Pregunta al portal con el token de quien está haciendo la petición en curso;
 * sin nadie detrás (una tarea programada, un comando de consola), desde la
 * 0.1.3, con el de este servidor (CallerToken), que solo existe en Cloud Run.
 */
final readonly class SuiteRecipients
{
    public function __construct(
        private Portal $portal,
        private CallerToken $token,
    ) {
    }

    /**
     * @return list<Recipient>
     *
     * @throws SessionRejected   si el portal no acepta el token
     * @throws PortalUnavailable si el portal no contesta, o no hay sesión y
     *                           este servidor no tiene token (en desarrollo)
     */
    public function withRole(string $role): array
    {
        return $this->portal->recipients($this->token->current(), $role);
    }

    /**
     * Todos los correos a los que avisar, sin repetir.
     *
     * @return list<string>
     */
    public function emailsWithRole(string $role): array
    {
        $emails = [];
        foreach ($this->withRole($role) as $recipient) {
            foreach ($recipient->getEmails() as $email) {
                $emails[$email] = true;
            }
        }

        return array_keys($emails);
    }
}
