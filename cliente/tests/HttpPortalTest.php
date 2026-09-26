<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Tests;

use Ampa\PortalCliente\Portal\HttpPortal;
use Ampa\PortalCliente\Portal\PortalUnavailable;
use Ampa\PortalCliente\Portal\SessionRejected;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Cómo se pregunta al portal por HTTP. Lo que contesta el portal está
 * probado en api/tests/Portal/Api/AccessApiTest; aquí, que el cliente lo pide
 * bien y entiende cada respuesta.
 */
final class HttpPortalTest extends TestCase
{
    #[Test]
    public function pregunta_por_esta_aplicacion_con_el_token_en_la_cabecera(): void
    {
        $response = new JsonMockResponse([
            'email' => 'a@b.com',
            'name' => 'A',
            'roles' => ['usuario'],
            'notificationEmails' => ['a@gmail.com'],
            'applications' => [['code' => 'tareas', 'name' => 'Tareas del AMPA', 'url' => 'https://tareas.ampa.test']],
        ]);
        $portal = new HttpPortal(new MockHttpClient($response), 'http://portal:8083/', 'listados');

        $access = $portal->access('el-token');

        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('http://portal:8083/api/acceso?aplicacion=listados', $response->getRequestUrl());
        self::assertContains('Authorization: Bearer el-token', $response->getRequestOptions()['headers']);
        self::assertSame('a@b.com', $access->getEmail());
        self::assertSame(['usuario'], $access->getRoles());
        self::assertSame(['a@gmail.com'], $access->getNotificationEmails());
        self::assertSame(
            [['code' => 'tareas', 'name' => 'Tareas del AMPA', 'url' => 'https://tareas.ampa.test']],
            array_map(static fn ($application) => $application->toArray(), $access->getApplications()),
        );
    }

    #[Test]
    public function un_portal_anterior_sin_aplicaciones_da_la_lista_vacia(): void
    {
        $response = new JsonMockResponse(['email' => 'a@b.com', 'name' => 'A', 'roles' => [], 'notificationEmails' => []]);

        self::assertSame([], $this->portal($response)->access('t')->getApplications());
    }

    #[Test]
    public function un_401_es_que_hay_que_volver_a_entrar(): void
    {
        $this->expectException(SessionRejected::class);

        $this->portal(new JsonMockResponse(['error' => 'no'], ['http_code' => 401]))->access('t');
    }

    #[Test]
    public function un_500_es_que_el_portal_no_esta_bien(): void
    {
        $this->expectException(PortalUnavailable::class);

        $this->portal(new MockResponse('ups', ['http_code' => 500]))->access('t');
    }

    #[Test]
    public function si_no_contesta_es_que_el_portal_no_esta_bien(): void
    {
        $this->expectException(PortalUnavailable::class);

        $this->portal(new MockResponse(info: ['error' => 'timeout']))->access('t');
    }

    #[Test]
    public function si_contesta_algo_que_no_es_json_tambien(): void
    {
        $this->expectException(PortalUnavailable::class);

        $this->portal(new MockResponse('<html>'))->access('t');
    }

    #[Test]
    public function pide_a_quien_avisar_por_rol(): void
    {
        $response = new JsonMockResponse([['name' => 'Presidencia', 'emails' => ['p@ampa.com', 'p@gmail.com']]]);

        $recipients = $this->portal($response)->recipients('t', 'admin');

        self::assertSame('http://portal/api/avisos?aplicacion=listados&rol=admin', $response->getRequestUrl());
        self::assertSame('Presidencia', $recipients[0]->getName());
        self::assertSame(['p@ampa.com', 'p@gmail.com'], $recipients[0]->getEmails());
    }

    #[Test]
    public function pide_quien_hay_en_la_aplicacion(): void
    {
        $response = new JsonMockResponse([
            ['name' => 'Alberto', 'email' => 'alberto@ampa.com', 'roles' => ['miembro'], 'notificationEmails' => ['alberto@gmail.com']],
            // Un portal anterior a la 0.1.2 no manda los correos de aviso.
            ['name' => 'Vocal', 'email' => 'vocal@ampa.com', 'roles' => ['miembro']],
        ]);

        $members = $this->portal($response)->members('t');

        self::assertSame('http://portal/api/personas?aplicacion=listados', $response->getRequestUrl());
        self::assertSame('alberto@ampa.com', $members[0]->getEmail());
        self::assertSame('Alberto', $members[0]->getName());
        self::assertSame(['miembro'], $members[0]->getRoles());
        self::assertSame(['alberto@gmail.com'], $members[0]->getNotificationEmails());
        self::assertSame(['vocal@ampa.com'], $members[1]->getNotificationEmails(), 'Sin correos de aviso, el de la cuenta.');
    }

    #[Test]
    public function sin_acceso_a_la_aplicacion_no_dice_quien_hay(): void
    {
        $this->expectException(SessionRejected::class);

        $this->portal(new JsonMockResponse(['error' => 'no'], ['http_code' => 403]))->members('t');
    }

    #[Test]
    public function sin_acceso_a_la_aplicacion_no_da_los_correos(): void
    {
        $this->expectException(SessionRejected::class);

        $this->portal(new JsonMockResponse(['error' => 'no'], ['http_code' => 403]))->recipients('t', 'admin');
    }

    private function portal(MockResponse $response): HttpPortal
    {
        return new HttpPortal(new MockHttpClient($response), 'http://portal', 'listados');
    }
}
