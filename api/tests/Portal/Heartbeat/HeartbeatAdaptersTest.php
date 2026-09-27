<?php

declare(strict_types=1);

namespace App\Tests\Portal\Heartbeat;

use App\Portal\Application\Heartbeat\HeartbeatFailed;
use App\Portal\Domain\Suite\Application;
use App\Portal\Infrastructure\Google\MetadataServer;
use App\Portal\Infrastructure\Heartbeat\CloudRunBackupLauncher;
use App\Portal\Infrastructure\Heartbeat\HttpApplicationWaker;
use App\Portal\Infrastructure\Heartbeat\NeonDatabaseUsage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Lo que el latido le pide a Google y a Neon, con un cliente HTTP falso que
 * apunta cada petición: se comprueba qué se pide y cómo se entiende la
 * respuesta.
 */
final class HeartbeatAdaptersTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    #[Test]
    public function despierta_a_una_aplicacion_con_un_token_para_su_direccion(): void
    {
        $http = $this->http([
            new MockResponse('token-para-tareas'),
            new MockResponse('{"ok":true}', ['http_code' => 200]),
        ]);

        (new HttpApplicationWaker($http, new MetadataServer($http)))->wake($this->tareas());

        self::assertStringContainsString('audience=https://tareas.ejemplo&', $this->requests[0]['url']);
        self::assertStringContainsString('format=full', $this->requests[0]['url']);
        self::assertSame('POST', $this->requests[1]['method']);
        self::assertSame('https://tareas.ejemplo/api/latido', $this->requests[1]['url']);
        self::assertContains('Authorization: Bearer token-para-tareas', $this->requests[1]['options']['headers']);
    }

    #[Test]
    public function una_aplicacion_que_contesta_con_error_es_un_fallo_con_su_respuesta(): void
    {
        $http = $this->http([
            new MockResponse('token'),
            new MockResponse('{"error":"se ha roto"}', ['http_code' => 500]),
        ]);

        $this->expectException(HeartbeatFailed::class);
        $this->expectExceptionMessage('ha contestado 500: {"error":"se ha roto"}');

        (new HttpApplicationWaker($http, new MetadataServer($http)))->wake($this->tareas());
    }

    #[Test]
    public function fuera_de_google_cloud_no_hay_token_y_es_un_fallo(): void
    {
        $http = $this->http([new MockResponse('', ['error' => 'Could not resolve host: metadata.google.internal'])]);

        $this->expectException(HeartbeatFailed::class);

        (new HttpApplicationWaker($http, new MetadataServer($http)))->wake($this->tareas());
    }

    #[Test]
    public function lanza_el_job_de_las_copias_con_la_api_de_cloud_run(): void
    {
        $http = $this->http([
            new MockResponse('{"access_token":"ya29.token","expires_in":3599,"token_type":"Bearer"}'),
            new MockResponse('{"name":"projects/p/locations/europe-west1/operations/op1","metadata":{"name":"projects/p/locations/europe-west1/jobs/ampa-copias/executions/ampa-copias-x7k2p"}}'),
        ]);

        $execution = (new CloudRunBackupLauncher($http, new MetadataServer($http), 'projects/p/locations/europe-west1/jobs/ampa-copias'))->launch();

        self::assertSame('ampa-copias-x7k2p', $execution);
        self::assertSame('https://run.googleapis.com/v2/projects/p/locations/europe-west1/jobs/ampa-copias:run', $this->requests[1]['url']);
        self::assertContains('Authorization: Bearer ya29.token', $this->requests[1]['options']['headers']);
    }

    #[Test]
    public function sin_permiso_para_lanzar_el_job_dice_lo_que_contesta_google(): void
    {
        $http = $this->http([
            new MockResponse('{"access_token":"ya29.token"}'),
            new MockResponse('{"error":{"code":403,"message":"Permission \'run.jobs.run\' denied"}}', ['http_code' => 403]),
        ]);

        $this->expectException(HeartbeatFailed::class);
        $this->expectExceptionMessage("Cloud Run ha contestado 403: Permission 'run.jobs.run' denied");

        (new CloudRunBackupLauncher($http, new MetadataServer($http), 'projects/p/locations/europe-west1/jobs/ampa-copias'))->launch();
    }

    #[Test]
    public function sin_job_las_copias_no_estan_configuradas(): void
    {
        $http = $this->http([]);

        self::assertFalse((new CloudRunBackupLauncher($http, new MetadataServer($http), ''))->isConfigured());
    }

    #[Test]
    public function lee_el_computo_de_neon_en_cu_horas(): void
    {
        $http = $this->http([new MockResponse((string) json_encode(['project' => [
            'id' => 'bold-sun-123',
            'compute_time_seconds' => 162000,
            'consumption_period_end' => '2026-10-01T00:00:00Z',
        ]]))]);

        $usage = (new NeonDatabaseUsage($http, 'bold-sun-123', 'clave', 100))->current();

        self::assertSame(45.0, $usage->getHoursUsed());
        self::assertSame('45,0 de 100 CU-horas; el periodo acaba el 01/10/2026', $usage->describe());
        self::assertSame('https://console.neon.tech/api/v2/projects/bold-sun-123', $this->requests[0]['url']);
        self::assertContains('Authorization: Bearer clave', $this->requests[0]['options']['headers']);
    }

    #[Test]
    public function si_neon_no_trae_el_computo_es_un_fallo_y_no_un_cero(): void
    {
        $http = $this->http([new MockResponse('{"project":{"id":"bold-sun-123"}}')]);

        $this->expectException(HeartbeatFailed::class);

        (new NeonDatabaseUsage($http, 'bold-sun-123', 'clave', 100))->current();
    }

    #[Test]
    public function con_una_clave_que_no_vale_es_un_fallo(): void
    {
        $http = $this->http([new MockResponse('{"message":"authorization failed"}', ['http_code' => 401])]);

        $this->expectException(HeartbeatFailed::class);
        $this->expectExceptionMessage('Neon ha contestado 401: authorization failed');

        (new NeonDatabaseUsage($http, 'bold-sun-123', 'mala', 100))->current();
    }

    #[Test]
    public function sin_clave_neon_no_esta_configurado(): void
    {
        self::assertFalse((new NeonDatabaseUsage($this->http([]), 'bold-sun-123', '', 100))->isConfigured());
    }

    private function tareas(): Application
    {
        return new Application('tareas', 'Tareas', 'https://tareas.ejemplo/', ['miembro' => 'Miembro'], heartbeat: true);
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function http(array $responses): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($responses) ?? throw new \LogicException('Petición de más: '.$url);
        });
    }
}
