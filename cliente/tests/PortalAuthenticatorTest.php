<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Tests;

use Ampa\PortalCliente\Portal\Portal;
use Ampa\PortalCliente\Portal\PortalAccess;
use Ampa\PortalCliente\Portal\PortalUnavailable;
use Ampa\PortalCliente\PortalSession;
use Ampa\PortalCliente\Security\PortalAuthenticator;
use Ampa\PortalCliente\Security\PortalRolesAsSymfonyRoles;
use Ampa\PortalCliente\Security\PortalUnavailableException;
use Ampa\PortalCliente\Security\SuiteCookie;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PortalAuthenticatorTest extends TestCase
{
    #[Test]
    public function si_el_portal_no_contesta_es_un_503_y_la_cookie_no_se_toca(): void
    {
        // La sesión puede estar perfectamente bien: borrarla obligaría a todo
        // el mundo a volver a entrar por una caída del portal.
        $authenticator = new PortalAuthenticator(new SuiteCookie('', true), new class implements Portal {
            public function access(string $token): PortalAccess
            {
                throw new PortalUnavailable('caído');
            }

            public function recipients(string $token, string $role): array
            {
                return [];
            }
        }, new PortalRolesAsSymfonyRoles());

        $request = Request::create('/api/me');
        $request->cookies->set(PortalSession::COOKIE, 't');

        try {
            $authenticator->authenticate($request);
            self::fail('Tenía que fallar.');
        } catch (PortalUnavailableException $e) {
            $response = $authenticator->onAuthenticationFailure($request, $e);
        }

        self::assertSame(503, $response->getStatusCode());
        self::assertSame([], $response->headers->getCookies());
    }
}
