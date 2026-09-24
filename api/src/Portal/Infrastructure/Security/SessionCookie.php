<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * La cookie que lleva la sesión de la suite.
 *
 * - `Domain=.ampasainzvicuna.com` (SESSION_COOKIE_DOMAIN): la pone el portal y
 *   el navegador la manda sola a /api de empleados., listados., etc. Por eso
 *   se entra una vez para toda la suite. Vacío en desarrollo: sin dominio, la
 *   cookie es de "localhost", y en localhost vale para todos los puertos.
 * - `HttpOnly`: el JavaScript de la página no puede leerla, así que un fallo de
 *   XSS en una aplicación no basta para robar la sesión.
 * - `Secure`: solo viaja por HTTPS.
 * - `SameSite=Lax`: el navegador no la manda en un POST que venga de otra web,
 *   que es el ataque de CSRF de siempre. (Hay una segunda barrera:
 *   CrossSiteRequestGuard.)
 *
 * El nombre es parte del contrato con el cliente que instalan las
 * aplicaciones (`Ampa\PortalCliente\PortalSession::COOKIE`): cambiarlo exige
 * cambiarlo allí y subir la segunda cifra de la versión.
 */
final readonly class SessionCookie
{
    public const string NAME = 'ampa_sesion';

    public function __construct(
        private string $domain,
        private bool $secure,
    ) {
    }

    public function create(string $token, int $ttl, \DateTimeImmutable $now): Cookie
    {
        return $this->cookie($token, $now->modify(sprintf('+%d seconds', $ttl)));
    }

    /** Una cookie ya caducada con el mismo nombre y dominio: el navegador la borra. */
    public function clear(): Cookie
    {
        return $this->cookie('', new \DateTimeImmutable('@1'));
    }

    public function tokenFrom(Request $request): ?string
    {
        $token = $request->cookies->get(self::NAME);

        return is_string($token) && '' !== $token ? $token : null;
    }

    private function cookie(string $value, \DateTimeImmutable $expires): Cookie
    {
        return Cookie::create(self::NAME)
            ->withValue($value)
            ->withExpires($expires)
            ->withPath('/')
            ->withDomain('' === $this->domain ? null : $this->domain)
            ->withSecure($this->secure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
