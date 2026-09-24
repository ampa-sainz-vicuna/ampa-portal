<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Tests;

use Ampa\PortalCliente\Portal\Recipient;
use Ampa\PortalCliente\Portal\SuiteRecipients;
use Ampa\PortalCliente\PortalSession;
use Ampa\PortalCliente\Testing\FakePortal;
use Ampa\PortalCliente\Tests\App\OpenedListener;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * El cliente instalado en una aplicación (tests/App/TestKernel), de punta a
 * punta: la cookie llega, se pregunta al portal (aquí, FakePortal) y la
 * aplicación responde según lo que diga.
 */
final class ApplicationTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        OpenedListener::$opened = [];
        FakePortal::reset();
    }

    #[Test]
    public function con_la_cookie_de_la_suite_sabe_quien_soy(): void
    {
        $this->signedIn('admin@ampasainzvicuna.com', 'Admin', ['usuario']);

        $this->client->request('GET', '/api/me');

        self::assertResponseIsSuccessful();
        self::assertSame(['name' => 'Admin', 'email' => 'admin@ampasainzvicuna.com'], $this->payload());
    }

    #[Test]
    public function abrir_la_aplicacion_avisa_a_quien_escuche(): void
    {
        $this->signedIn('admin@ampasainzvicuna.com', 'Admin', ['usuario']);

        $this->client->request('GET', '/api/me');

        self::assertSame(['admin@ampasainzvicuna.com'], OpenedListener::$opened);
    }

    #[Test]
    public function los_roles_del_portal_son_roles_de_symfony(): void
    {
        $this->signedIn('admin@ampasainzvicuna.com', 'Admin', ['usuario', 'admin'], ['admin@gmail.com']);

        $this->client->request('GET', '/api/admin/algo');

        self::assertResponseIsSuccessful();
        self::assertSame(['ROLE_USUARIO', 'ROLE_ADMIN'], $this->payload()['roles']);
        self::assertSame(['admin@gmail.com'], $this->payload()['notificationEmails']);
    }

    #[Test]
    public function sin_el_rol_que_pide_la_ruta_es_un_403_en_json(): void
    {
        $this->signedIn('usuario@ampasainzvicuna.com', 'Usuario', ['usuario']);

        $this->client->request('GET', '/api/admin/algo');

        self::assertResponseStatusCodeSame(403);
        self::assertSame('No tienes permiso para esto.', $this->payload()['error']);
    }

    #[Test]
    public function sin_ningun_rol_en_esta_aplicacion_es_un_403_y_la_cookie_se_queda(): void
    {
        // La sesión vale para la suite: borrarla le sacaría también de las
        // aplicaciones en las que sí entra.
        $this->signedIn('fichajes@ampasainzvicuna.com', 'Solo fichajes', []);

        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(403);
        self::assertSame('No tienes acceso a esta aplicación.', $this->payload()['error']);
        self::assertNull($this->sessionCookie());
    }

    #[Test]
    public function sin_cookie_hay_que_entrar(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function si_el_portal_no_acepta_la_sesion_es_un_401_y_se_borra_la_cookie_del_dominio_de_la_suite(): void
    {
        $this->client->getCookieJar()->set(new BrowserCookie(PortalSession::COOKIE, 'caducada'));

        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
        $cookie = $this->sessionCookie();
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isCleared());
        // Si el dominio no fuera el mismo con que la puso el portal, el
        // navegador no la borraría.
        self::assertSame('.ampasainzvicuna.com', $cookie->getDomain());
    }

    #[Test]
    public function salir_borra_la_cookie_de_la_suite(): void
    {
        $this->client->request('POST', '/api/auth/salir');

        self::assertResponseStatusCodeSame(204);
        self::assertTrue($this->sessionCookie()?->isCleared() ?? false);
    }

    #[Test]
    public function un_post_que_viene_de_otra_web_se_rechaza_antes_de_preguntar_al_portal(): void
    {
        $this->signedIn('admin@ampasainzvicuna.com', 'Admin', ['usuario']);

        $this->client->request('POST', '/api/guardar', server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function desde_la_misma_pagina_si_se_acepta(): void
    {
        $this->signedIn('admin@ampasainzvicuna.com', 'Admin', ['usuario']);

        $this->client->request('POST', '/api/guardar', server: ['HTTP_SEC_FETCH_SITE' => 'same-origin']);

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function a_quien_avisar_se_pregunta_con_la_sesion_de_la_peticion_en_curso(): void
    {
        FakePortal::recipientsFor('admin', [
            new Recipient('Presidencia', ['presidencia@ampasainzvicuna.com', 'presi@gmail.com']),
            new Recipient('Tesorería', ['presi@gmail.com']),
        ]);

        $request = Request::create('/api/lo-que-sea');
        $request->cookies->set(PortalSession::COOKIE, FakePortal::session('empleada@ampasainzvicuna.com', 'Empleada', ['usuario']));
        self::getContainer()->get('request_stack')->push($request);

        /** @var SuiteRecipients $recipients */
        $recipients = self::getContainer()->get('test.suite_recipients');
        $emails = $recipients->emailsWithRole('admin');

        // Sin repetir: el mismo correo personal en dos fichas llega una vez.
        self::assertSame(['presidencia@ampasainzvicuna.com', 'presi@gmail.com'], $emails);
    }

    /**
     * @param list<string> $roles
     * @param list<string> $notificationEmails
     */
    private function signedIn(string $email, string $name, array $roles, array $notificationEmails = []): void
    {
        $this->client->getCookieJar()->set(new BrowserCookie(PortalSession::COOKIE, FakePortal::session($email, $name, $roles, $notificationEmails)));
    }

    /** @return array<mixed> */
    private function payload(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function sessionCookie(): ?Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if (PortalSession::COOKIE === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }
}
