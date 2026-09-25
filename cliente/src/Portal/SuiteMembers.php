<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

use Ampa\PortalCliente\Security\SuiteCookie;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Quién hay en esta aplicación, para usar desde un caso de uso o un
 * controlador:
 *
 *   $members->all()  // las personas activas con algún rol aquí, por nombre
 *
 * Por ejemplo, tareas lo usa para elegir a quién se asigna una tarea. Como
 * SuiteRecipients, pregunta al portal con el token de quien hace la petición
 * en curso, así que solo funciona dentro de una petición con sesión.
 */
final readonly class SuiteMembers
{
    public function __construct(
        private Portal $portal,
        private SuiteCookie $cookie,
        private RequestStack $requests,
    ) {
    }

    /**
     * @return list<Member>
     *
     * @throws SessionRejected   si no hay sesión en la petición en curso
     * @throws PortalUnavailable si el portal no contesta
     */
    public function all(): array
    {
        $request = $this->requests->getMainRequest();
        $token = null === $request ? null : $this->cookie->tokenFrom($request);

        if (null === $token) {
            throw new SessionRejected('No hay sesión en esta petición: sin ella no se puede preguntar al portal.');
        }

        return $this->portal->members($token);
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
