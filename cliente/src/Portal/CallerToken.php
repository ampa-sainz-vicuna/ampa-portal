<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

use Ampa\PortalCliente\Security\SuiteCookie;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Con qué token preguntar al portal: el de la sesión de quien hace la petición
 * en curso y, si no hay ninguna (una tarea programada, un comando de consola),
 * el de este servidor (desde la 0.1.3).
 *
 * Lo usan SuiteRecipients y SuiteMembers. PortalAuthenticator no: quién entra
 * es siempre una persona.
 */
final readonly class CallerToken
{
    public function __construct(
        private SuiteCookie $cookie,
        private RequestStack $requests,
        private ApplicationIdentity $identity,
    ) {
    }

    /**
     * @throws PortalUnavailable si no hay sesión y no se ha podido conseguir el de este servidor
     */
    public function current(): string
    {
        $request = $this->requests->getMainRequest();

        return (null === $request ? null : $this->cookie->tokenFrom($request)) ?? $this->identity->token();
    }
}
