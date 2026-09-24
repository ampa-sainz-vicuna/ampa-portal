<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Security;

use Ampa\PortalCliente\PortalSession;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * La cookie de sesión de la suite, vista desde una aplicación: la lee y, al
 * salir o si el portal la rechaza, la borra.
 *
 * La pone siempre el portal. Para borrarla, el navegador exige el MISMO nombre,
 * dominio y ruta con que se puso: por eso el dominio viene de la configuración
 * (`cookie_domain`) y tiene que ser el mismo que el del portal.
 */
final readonly class SuiteCookie
{
    public function __construct(
        private string $domain,
        private bool $secure,
    ) {
    }

    public function tokenFrom(Request $request): ?string
    {
        $token = $request->cookies->get(PortalSession::COOKIE);

        return is_string($token) && '' !== $token ? $token : null;
    }

    public function clear(): Cookie
    {
        return Cookie::create(PortalSession::COOKIE)
            ->withValue('')
            ->withExpires(new \DateTimeImmutable('@1'))
            ->withPath('/')
            ->withDomain('' === $this->domain ? null : $this->domain)
            ->withSecure($this->secure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
