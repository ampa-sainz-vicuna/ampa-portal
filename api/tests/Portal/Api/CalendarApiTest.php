<?php

declare(strict_types=1);

namespace App\Tests\Portal\Api;

use App\Portal\Domain\Calendar\SchoolYear;
use App\Portal\Domain\Calendar\SchoolYearRepository;
use App\Tests\Double\FakeServiceAccountVerifier;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Clock\MockClock;

/**
 * El calendario escolar común: la pantalla que lo carga (/api/admin/calendario)
 * y lo que preguntan las aplicaciones (/api/calendario, contrato con el
 * cliente 0.1.5).
 */
final class CalendarApiTest extends ApiTestCase
{
    private const array COURSE = [
        'classesStart' => '2026-09-08',
        'classesEnd' => '2027-06-18',
        'periods' => [
            ['from' => '2026-12-23', 'to' => '2027-01-07', 'kind' => 'non_school', 'name' => 'Navidad'],
            ['from' => '2026-10-12', 'to' => '2026-10-12', 'kind' => 'holiday', 'name' => 'Día de la Hispanidad'],
        ],
    ];

    #[Test]
    public function quien_gestiona_los_permisos_carga_un_curso_y_lo_ve_en_la_lista(): void
    {
        $this->signedInAdmin();

        $this->request('PUT', '/api/admin/calendario/2026-2027', self::COURSE);

        self::assertSame(200, $this->responseStatus());
        self::assertSame(['Día de la Hispanidad', 'Navidad'], array_column($this->payload()['periods'], 'name'));

        $this->request('GET', '/api/admin/calendario');

        self::assertSame(['2026-2027'], array_column($this->payload(), 'schoolYear'));
    }

    #[Test]
    public function guardarlo_otra_vez_lo_sustituye_entero(): void
    {
        $this->signedInAdmin();
        $this->request('PUT', '/api/admin/calendario/2026-2027', self::COURSE);

        $this->request('PUT', '/api/admin/calendario/2026-2027', [...self::COURSE, 'periods' => []]);

        $this->request('GET', '/api/admin/calendario');
        self::assertSame([], $this->payload()[0]['periods']);
    }

    #[Test]
    public function un_curso_imposible_es_un_422_con_el_motivo(): void
    {
        $this->signedInAdmin();

        $this->request('PUT', '/api/admin/calendario/2026-2027', [...self::COURSE, 'classesStart' => '2026-08-01']);

        self::assertSame(422, $this->responseStatus());
        self::assertStringContainsString('tienen que estar entre', $this->payload()['error']);
    }

    #[Test]
    public function sin_la_forma_que_toca_es_un_400(): void
    {
        $this->signedInAdmin();

        $this->request('PUT', '/api/admin/calendario/2026-2027', [...self::COURSE, 'periods' => [['from' => '2026-10-12', 'kind' => 'fiesta']]]);

        self::assertSame(400, $this->responseStatus());
    }

    #[Test]
    public function sin_gestionar_los_permisos_no_se_puede_cargar(): void
    {
        $this->given('vocal@ampasainzvicuna.com', ['listados' => ['usuario']]);
        $this->signedInAs('vocal@ampasainzvicuna.com');

        $this->request('PUT', '/api/admin/calendario/2026-2027', self::COURSE);

        self::assertSame(403, $this->responseStatus());
    }

    #[Test]
    public function una_aplicacion_lo_pide_con_la_sesion_de_cualquiera_de_la_suite(): void
    {
        $this->loadCourse();
        $this->given('alberto@ampasainzvicuna.com', ['fichajes' => ['empleado']]);

        $this->request('GET', '/api/calendario?curso=2026-2027', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('alberto@ampasainzvicuna.com')]);

        self::assertSame(200, $this->responseStatus());
        self::assertSame([
            'schoolYear' => '2026-2027',
            'classesStart' => '2026-09-08',
            'classesEnd' => '2027-06-18',
            'periods' => [
                ['from' => '2026-10-12', 'to' => '2026-10-12', 'kind' => 'holiday', 'name' => 'Día de la Hispanidad'],
                ['from' => '2026-12-23', 'to' => '2027-01-07', 'kind' => 'non_school', 'name' => 'Navidad'],
            ],
        ], $this->payload());
    }

    #[Test]
    public function y_sin_nadie_detras_con_el_token_de_la_cuenta_de_servicio(): void
    {
        $this->loadCourse();

        $this->request('GET', '/api/calendario?curso=2026-2027', server: ['HTTP_AUTHORIZATION' => 'Bearer '.FakeServiceAccountVerifier::TOKEN]);

        self::assertSame(200, $this->responseStatus());
    }

    #[Test]
    public function un_curso_sin_cargar_es_un_404(): void
    {
        $this->given('alberto@ampasainzvicuna.com', ['fichajes' => ['empleado']]);

        $this->request('GET', '/api/calendario?curso=2027-2028', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('alberto@ampasainzvicuna.com')]);

        self::assertSame(404, $this->responseStatus());
    }

    #[Test]
    public function sin_token_es_un_401(): void
    {
        $this->request('GET', '/api/calendario?curso=2026-2027');

        self::assertSame(401, $this->responseStatus());
    }

    #[Test]
    public function abrir_el_publico_no_abre_el_resto_del_calendario(): void
    {
        $this->request('GET', '/api/calendario');
        self::assertSame(401, $this->responseStatus());

        $this->request('GET', '/api/admin/calendario');
        self::assertSame(401, $this->responseStatus());

        $this->request('GET', '/api/calendario/2026-2027');
        self::assertNotSame(200, $this->responseStatus());
    }

    #[Test]
    public function el_publico_trae_el_siguiente_aunque_el_de_hoy_no_este_cargado(): void
    {
        $this->todayIs('2026-07-15');
        $this->loadCourses('2026-2027');

        $this->request('GET', '/api/calendario/publico');

        self::assertSame(['2026-2027'], array_column($this->payload()['schoolYears'], 'schoolYear'));
    }

    #[Test]
    public function el_publico_cambia_de_curso_a_medianoche_de_madrid_no_de_utc(): void
    {
        // 22:30 UTC del 31 de agosto: en Madrid ya es 1 de septiembre.
        self::getContainer()->set('clock', new MockClock('2026-08-31 22:30:00', 'UTC'));
        $this->loadCourses('2025-2026', '2026-2027');

        $this->request('GET', '/api/calendario/publico');

        self::assertSame(['2026-2027'], array_column($this->payload()['schoolYears'], 'schoolYear'));
    }

    #[Test]
    public function el_publico_no_pide_sesion_y_trae_el_curso_de_hoy_y_los_posteriores_en_orden(): void
    {
        $this->todayIs('2026-10-08');
        $this->loadCourses('2027-2028', '2025-2026', '2026-2027');

        $this->request('GET', '/api/calendario/publico');

        self::assertSame(200, $this->responseStatus());
        self::assertSame(['2026-2027', '2027-2028'], array_column($this->payload()['schoolYears'], 'schoolYear'));
        self::assertSame('2026-09-08', $this->payload()['schoolYears'][0]['classesStart']);
    }

    #[Test]
    public function el_publico_cambia_de_curso_el_1_de_septiembre(): void
    {
        $this->todayIs('2026-08-31');
        $this->loadCourses('2025-2026', '2026-2027');

        $this->request('GET', '/api/calendario/publico');

        self::assertSame(['2025-2026', '2026-2027'], array_column($this->payload()['schoolYears'], 'schoolYear'));
    }

    #[Test]
    public function el_publico_se_puede_cachear_una_hora(): void
    {
        $this->todayIs('2026-10-08');

        $this->request('GET', '/api/calendario/publico');

        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('max-age=3600', $cacheControl);
    }

    #[Test]
    public function el_publico_sin_cursos_vigentes_es_una_lista_vacia(): void
    {
        $this->todayIs('2030-10-08');
        $this->loadCourses('2026-2027');

        $this->request('GET', '/api/calendario/publico');

        self::assertSame(200, $this->responseStatus());
        self::assertSame(['schoolYears' => []], $this->payload());
    }

    private function todayIs(string $day): void
    {
        // Antes de la primera petición: el reloj lo usa el controlador.
        self::getContainer()->set('clock', new MockClock($day.' 12:00:00'));
    }

    /** Guarda cursos "AAAA-BBBB" con su calendario mínimo, directos en el repositorio. */
    private function loadCourses(string ...$codes): void
    {
        $repository = self::getContainer()->get(SchoolYearRepository::class);
        foreach ($codes as $code) {
            $start = (int) substr($code, 0, 4);
            $repository->save(SchoolYear::define($code, $start.'-09-08', ($start + 1).'-06-18', []));
        }
    }

    private function signedInAdmin(): void
    {
        $this->given('admin@ampasainzvicuna.com', ['portal' => ['admin']]);
        $this->signedInAs('admin@ampasainzvicuna.com');
    }

    private function loadCourse(): void
    {
        $this->signedInAdmin();
        $this->request('PUT', '/api/admin/calendario/2026-2027', self::COURSE);
        $this->client->getCookieJar()->clear();
    }
}
