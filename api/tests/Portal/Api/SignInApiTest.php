<?php

declare(strict_types=1);

namespace App\Tests\Portal\Api;

use App\Portal\Domain\User\UserRepository;
use App\Portal\Infrastructure\Security\SessionCookie;
use App\Tests\Double\FakeIdentityVerifier;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Entrar, saber quién soy y salir: la sesión de toda la suite.
 */
final class SignInApiTest extends ApiTestCase
{
    #[Test]
    public function entrar_pone_la_cookie_de_sesion_y_dice_a_donde_puede_ir(): void
    {
        $admin = $this->given('admin@ampasainzvicuna.com', ['listados' => ['usuario'], 'portal' => ['admin']]);

        $this->request('POST', '/api/auth/google', ['credential' => 'admin@ampasainzvicuna.com']);

        self::assertSame(200, $this->responseStatus());
        // Y queda apuntado que ha entrado.
        self::assertNotNull($this->reload($admin)->getLastSeenAt());
        $payload = $this->payload();
        self::assertSame('admin@ampasainzvicuna.com', $payload['email']);
        self::assertTrue($payload['isAdmin']);
        self::assertSame(['listados'], array_column($payload['applications'], 'code'));
        self::assertSame('http://localhost:5174', $payload['applications'][0]['url']);

        // El token solo en la cookie: nunca en el cuerpo, donde lo vería el JavaScript.
        self::assertArrayNotHasKey('token', $payload);
        $cookie = $this->sessionCookie();
        self::assertNotSame('', $cookie->getValue());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        self::assertSame('/', $cookie->getPath());
        self::assertGreaterThan(time() + 6 * 86400, $cookie->getExpiresTime());
    }

    #[Test]
    public function con_esa_cookie_ya_sabe_quien_soy(): void
    {
        $this->given('admin@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->request('POST', '/api/auth/google', ['credential' => 'admin@ampasainzvicuna.com']);

        // El cliente de pruebas guarda la cookie y la manda, como un navegador.
        $this->request('GET', '/api/me');

        self::assertSame(200, $this->responseStatus());
        self::assertSame('admin@ampasainzvicuna.com', $this->payload()['email']);
        self::assertFalse($this->payload()['isAdmin']);
    }

    #[Test]
    public function si_google_no_lo_confirma_es_un_401(): void
    {
        $this->request('POST', '/api/auth/google', ['credential' => FakeIdentityVerifier::INVALID]);

        self::assertSame(401, $this->responseStatus());
    }

    #[Test]
    public function sin_credential_es_un_400(): void
    {
        $this->request('POST', '/api/auth/google', []);

        self::assertSame(400, $this->responseStatus());
    }

    #[Test]
    public function quien_no_esta_dado_de_alta_no_entra(): void
    {
        $this->request('POST', '/api/auth/google', ['credential' => 'desconocido@gmail.com']);

        self::assertSame(403, $this->responseStatus());
        self::assertNull($this->sessionCookieOrNull());
    }

    #[Test]
    public function quien_esta_desactivado_no_entra(): void
    {
        $this->given('antiguo@ampasainzvicuna.com', ['listados' => ['usuario']], active: false);

        $this->request('POST', '/api/auth/google', ['credential' => 'antiguo@ampasainzvicuna.com']);

        self::assertSame(403, $this->responseStatus());
    }

    #[Test]
    public function quien_no_tiene_ningun_permiso_no_entra(): void
    {
        $this->given('nada@ampasainzvicuna.com', []);

        $this->request('POST', '/api/auth/google', ['credential' => 'nada@ampasainzvicuna.com']);

        self::assertSame(403, $this->responseStatus());
    }

    #[Test]
    public function sin_cookie_hay_que_entrar(): void
    {
        $this->request('GET', '/api/me');

        self::assertSame(401, $this->responseStatus());
    }

    #[Test]
    public function una_cookie_que_no_vale_da_401_y_se_borra(): void
    {
        $this->client->getCookieJar()->set(new BrowserCookie(SessionCookie::NAME, 'basura'));

        $this->request('GET', '/api/me');

        self::assertSame(401, $this->responseStatus());
        self::assertTrue($this->sessionCookie()->isCleared());
    }

    #[Test]
    public function desactivar_a_alguien_le_cierra_la_puerta_aunque_su_cookie_siga_vigente(): void
    {
        $user = $this->given('admin@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->signedInAs('admin@ampasainzvicuna.com');

        $user->deactivate();
        self::getContainer()->get(UserRepository::class)->save($user);

        $this->request('GET', '/api/me');

        self::assertSame(401, $this->responseStatus());
    }

    #[Test]
    public function salir_borra_la_cookie(): void
    {
        $this->request('POST', '/api/auth/salir');

        self::assertSame(204, $this->responseStatus());
        self::assertTrue($this->sessionCookie()->isCleared());
    }

    #[Test]
    public function un_post_que_viene_de_otra_web_se_rechaza(): void
    {
        $this->given('admin@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->signedInAs('admin@ampasainzvicuna.com');

        $this->request('PUT', '/api/me/contacto', ['secondaryEmail' => 'atacante@example.com', 'notify' => 'secondary'], ['HTTP_SEC_FETCH_SITE' => 'cross-site']);

        self::assertSame(403, $this->responseStatus());
    }

    #[Test]
    public function tampoco_si_viene_de_otro_subdominio(): void
    {
        $this->request('POST', '/api/auth/salir', null, ['HTTP_SEC_FETCH_SITE' => 'same-site']);

        self::assertSame(403, $this->responseStatus());
    }

    #[Test]
    public function desde_la_misma_pagina_si_se_acepta(): void
    {
        $this->request('POST', '/api/auth/salir', null, ['HTTP_SEC_FETCH_SITE' => 'same-origin']);

        self::assertSame(204, $this->responseStatus());
    }

    private function sessionCookie(): Cookie
    {
        return $this->sessionCookieOrNull() ?? throw new \LogicException('La respuesta no pone la cookie de sesión.');
    }

    private function sessionCookieOrNull(): ?Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if (SessionCookie::NAME === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }
}
