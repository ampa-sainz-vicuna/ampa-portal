<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

use Ampa\PortalCliente\Security\SuiteCookie;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A quién avisar en esta aplicación, para usar desde un caso de uso:
 *
 *   $recipients->withRole('admin')  // los administradores, cada uno en el correo que eligió
 *
 * Pregunta al portal con el token de quien está haciendo la petición en curso,
 * así que solo funciona dentro de una petición con sesión (no desde un
 * comando de consola).
 */
final readonly class SuiteRecipients
{
    public function __construct(
        private Portal $portal,
        private SuiteCookie $cookie,
        private RequestStack $requests,
    ) {
    }

    /**
     * @return list<Recipient>
     *
     * @throws SessionRejected   si no hay sesión en la petición en curso
     * @throws PortalUnavailable si el portal no contesta
     */
    public function withRole(string $role): array
    {
        $request = $this->requests->getMainRequest();
        $token = null === $request ? null : $this->cookie->tokenFrom($request);

        if (null === $token) {
            throw new SessionRejected('No hay sesión en esta petición: sin ella no se puede preguntar al portal.');
        }

        return $this->portal->recipients($token, $role);
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
