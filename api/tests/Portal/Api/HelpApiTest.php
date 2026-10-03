<?php

declare(strict_types=1);

namespace App\Tests\Portal\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * La ayuda de la suite: la pantalla que la carga (/api/admin/ayuda) y lo que
 * pide el botón «Ayuda» de cada aplicación (/api/ayuda, contrato con
 * @ampa/ui), con sus cabeceras de CORS.
 */
final class HelpApiTest extends ApiTestCase
{
    private const array FILE = [
        'version' => 1,
        'updatedAt' => '2026-09-28',
        'notebookUrl' => 'https://notebooklm.example.com/notebook/prueba',
        'contactEmail' => 'ayuda@example.com',
        'entries' => [
            ['id' => 'general-avisos', 'application' => 'general', 'roles' => [], 'question' => '¿A dónde me llegan los avisos?', 'answer' => "A tu correo.\nSe cambia en el portal.", 'keywords' => ['correo'], 'manual' => null],
            ['id' => 'facturacion-cerrar-mes', 'application' => 'facturacion', 'roles' => [], 'question' => '¿Cómo cierro el mes?', 'answer' => 'Así.', 'keywords' => ['cierre'], 'manual' => '06'],
            ['id' => 'fichajes-corregir', 'application' => 'fichajes', 'roles' => ['admin'], 'question' => '¿Cómo corrijo un fichaje?', 'answer' => 'Así.', 'keywords' => [], 'manual' => '03'],
            ['id' => 'fichajes-fichar', 'application' => 'fichajes', 'roles' => [], 'question' => '¿Cómo ficho?', 'answer' => 'Así.', 'keywords' => [], 'manual' => '03'],
        ],
    ];

    #[Test]
    public function quien_gestiona_los_permisos_carga_el_fichero_y_ve_el_recuento(): void
    {
        $this->signedInAdmin();

        $this->request('PUT', '/api/admin/ayuda', self::FILE);

        self::assertSame(200, $this->responseStatus());
        $summary = $this->payload();
        self::assertSame(4, $summary['total']);
        self::assertSame('2026-09-28', $summary['updatedAt']);
        self::assertSame('https://notebooklm.example.com/notebook/prueba', $summary['notebookUrl']);
        self::assertNotNull($summary['loadedAt']);
        self::assertSame(
            ['general' => 1, 'fichajes' => 2, 'listados' => 0, 'facturacion' => 1, 'tareas' => 0, 'crm' => 0, 'documentos' => 0, 'portal' => 0],
            array_column($summary['byApplication'], 'entries', 'application'),
        );

        $this->request('GET', '/api/admin/ayuda');

        // El mismo instante, aunque al leerlo de la base venga en otra zona.
        $stored = $this->payload();
        self::assertEquals(new \DateTimeImmutable($summary['loadedAt']), new \DateTimeImmutable($stored['loadedAt']));
        unset($summary['loadedAt'], $stored['loadedAt']);
        self::assertSame($summary, $stored);
    }

    #[Test]
    public function sin_nada_cargado_el_resumen_esta_a_cero(): void
    {
        $this->signedInAdmin();

        $this->request('GET', '/api/admin/ayuda');

        self::assertSame(200, $this->responseStatus());
        self::assertNull($this->payload()['loadedAt']);
        self::assertSame(0, $this->payload()['total']);
    }

    #[Test]
    public function cargarlo_otra_vez_lo_sustituye_entero(): void
    {
        $this->loadFile();
        $this->signedInAs('admin@ampasainzvicuna.com');

        $this->request('PUT', '/api/admin/ayuda', [...self::FILE, 'notebookUrl' => null, 'entries' => [self::FILE['entries'][1]]]);

        $this->request('GET', '/api/ayuda');
        self::assertSame(['facturacion-cerrar-mes'], array_column($this->payload()['entries'], 'id'));
        self::assertNull($this->payload()['notebookUrl']);
    }

    #[Test]
    public function un_fichero_que_no_vale_es_un_422_con_el_motivo_y_no_cambia_nada(): void
    {
        $this->loadFile();
        $this->signedInAs('admin@ampasainzvicuna.com');

        $this->request('PUT', '/api/admin/ayuda', [...self::FILE, 'entries' => [...self::FILE['entries'], ['id' => 'x', 'application' => 'listados', 'roles' => ['admin'], 'question' => 'P', 'answer' => 'R']]]);

        self::assertSame(422, $this->responseStatus());
        self::assertSame('"x": la aplicación "listados" no tiene el rol "admin".', $this->payload()['error']);

        $this->request('GET', '/api/admin/ayuda');
        self::assertSame(4, $this->payload()['total']);
    }

    #[Test]
    public function un_cuerpo_que_no_es_json_es_un_400(): void
    {
        $this->signedInAdmin();

        $this->client->request('PUT', '/api/admin/ayuda', server: ['CONTENT_TYPE' => 'application/json'], content: '{"version": 1, ');

        self::assertSame(400, $this->responseStatus());
    }

    #[Test]
    public function sin_gestionar_los_permisos_no_se_puede_cargar_ni_ver_el_resumen(): void
    {
        $this->given('vocal@ampasainzvicuna.com', ['facturacion' => ['usuario']]);
        $this->signedInAs('vocal@ampasainzvicuna.com');

        $this->request('PUT', '/api/admin/ayuda', self::FILE);
        self::assertSame(403, $this->responseStatus());

        $this->request('GET', '/api/admin/ayuda');
        self::assertSame(403, $this->responseStatus());
    }

    #[Test]
    public function cada_uno_ve_las_generales_y_las_de_sus_aplicaciones_sin_los_roles(): void
    {
        $this->loadFile();
        $this->given('tesoreria@ampasainzvicuna.com', ['facturacion' => ['usuario']]);
        $this->signedInAs('tesoreria@ampasainzvicuna.com');

        $this->request('GET', '/api/ayuda');

        self::assertSame(200, $this->responseStatus());
        self::assertSame([
            'notebookUrl' => 'https://notebooklm.example.com/notebook/prueba',
            'contactEmail' => 'ayuda@example.com',
            'updatedAt' => '2026-09-28',
            'entries' => [
                ['id' => 'general-avisos', 'application' => 'general', 'question' => '¿A dónde me llegan los avisos?', 'answer' => "A tu correo.\nSe cambia en el portal.", 'keywords' => ['correo'], 'manual' => null],
                ['id' => 'facturacion-cerrar-mes', 'application' => 'facturacion', 'question' => '¿Cómo cierro el mes?', 'answer' => 'Así.', 'keywords' => ['cierre'], 'manual' => '06'],
            ],
        ], $this->payload());
    }

    #[Test]
    public function las_que_llevan_roles_solo_las_ve_quien_tiene_uno(): void
    {
        $this->loadFile();
        $this->given('alberto@ampasainzvicuna.com', ['fichajes' => ['empleado']]);
        $this->given('tesoreria@ampasainzvicuna.com', ['fichajes' => ['admin']]);

        $this->signedInAs('alberto@ampasainzvicuna.com');
        $this->request('GET', '/api/ayuda');
        self::assertSame(['general-avisos', 'fichajes-fichar'], array_column($this->payload()['entries'], 'id'));

        $this->signedInAs('tesoreria@ampasainzvicuna.com');
        $this->request('GET', '/api/ayuda');
        self::assertSame(['general-avisos', 'fichajes-corregir', 'fichajes-fichar'], array_column($this->payload()['entries'], 'id'));
    }

    #[Test]
    public function sin_nada_cargado_es_un_200_vacio(): void
    {
        $this->given('vocal@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->signedInAs('vocal@ampasainzvicuna.com');

        $this->request('GET', '/api/ayuda');

        self::assertSame(200, $this->responseStatus());
        self::assertSame(['notebookUrl' => null, 'contactEmail' => null, 'updatedAt' => null, 'entries' => []], $this->payload());
    }

    #[Test]
    public function sin_sesion_es_un_401_que_la_otra_web_puede_leer(): void
    {
        $this->request('GET', '/api/ayuda', server: ['HTTP_ORIGIN' => 'http://localhost:5175']);

        self::assertSame(401, $this->responseStatus());
        // Sin estas cabeceras, el navegador lo convertiría en un error de red
        // y la aplicación no sabría que hay que volver a entrar.
        self::assertSame('http://localhost:5175', $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function una_aplicacion_de_la_suite_la_puede_leer_desde_su_web(): void
    {
        $this->given('vocal@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->signedInAs('vocal@ampasainzvicuna.com');

        // URL_LISTADOS en desarrollo (api/.env).
        $this->request('GET', '/api/ayuda', server: ['HTTP_ORIGIN' => 'http://localhost:5174', 'HTTP_SEC_FETCH_SITE' => 'same-site']);

        $headers = $this->client->getResponse()->headers;
        self::assertSame(200, $this->responseStatus());
        self::assertSame('http://localhost:5174', $headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $headers->get('Access-Control-Allow-Credentials'));
        self::assertContains('Origin', $headers->all('vary'));
    }

    #[Test]
    public function otra_web_no_la_puede_leer(): void
    {
        $this->given('vocal@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->signedInAs('vocal@ampasainzvicuna.com');

        $this->request('GET', '/api/ayuda', server: ['HTTP_ORIGIN' => 'https://ampa-falso.example', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);

        $headers = $this->client->getResponse()->headers;
        self::assertNull($headers->get('Access-Control-Allow-Origin'));
        self::assertNull($headers->get('Access-Control-Allow-Credentials'));
        self::assertContains('Origin', $headers->all('vary'));
    }

    #[Test]
    public function la_peticion_previa_se_contesta_sin_sesion_solo_a_la_suite(): void
    {
        $this->request('OPTIONS', '/api/ayuda', server: ['HTTP_ORIGIN' => 'http://localhost:5175', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET']);

        self::assertSame(204, $this->responseStatus());
        self::assertSame('http://localhost:5175', $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('GET', $this->client->getResponse()->headers->get('Access-Control-Allow-Methods'));

        $this->request('OPTIONS', '/api/ayuda', server: ['HTTP_ORIGIN' => 'https://ampa-falso.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET']);

        self::assertSame(204, $this->responseStatus());
        self::assertNull($this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function las_demas_rutas_no_llevan_cors(): void
    {
        $this->given('vocal@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->signedInAs('vocal@ampasainzvicuna.com');

        $this->request('GET', '/api/me', server: ['HTTP_ORIGIN' => 'http://localhost:5174']);

        self::assertSame(200, $this->responseStatus());
        self::assertNull($this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    private function signedInAdmin(): void
    {
        $this->given('admin@ampasainzvicuna.com', ['portal' => ['admin'], 'facturacion' => ['usuario']]);
        $this->signedInAs('admin@ampasainzvicuna.com');
    }

    private function loadFile(): void
    {
        $this->signedInAdmin();
        $this->request('PUT', '/api/admin/ayuda', self::FILE);
        self::assertSame(200, $this->responseStatus());
        $this->client->getCookieJar()->clear();
    }
}
