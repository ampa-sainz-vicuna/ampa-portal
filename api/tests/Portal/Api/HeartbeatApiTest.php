<?php

declare(strict_types=1);

namespace App\Tests\Portal\Api;

use App\Portal\Application\Heartbeat\BackupLauncher;
use App\Tests\Double\FakeBackupLauncher;
use App\Tests\Double\FakeServiceAccountVerifier;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * POST /api/latido: lo que ve Cloud Scheduler. Lo que hace cada paso, en
 * RunHeartbeatTest.
 */
final class HeartbeatApiTest extends ApiTestCase
{
    #[Test]
    public function con_el_token_de_la_cuenta_de_servicio_hace_el_latido_y_dice_cada_paso(): void
    {
        $backups = self::getContainer()->get(BackupLauncher::class);
        \assert($backups instanceof FakeBackupLauncher);
        $backups->configured();

        $this->request('POST', '/api/latido', server: ['HTTP_AUTHORIZATION' => 'Bearer '.FakeServiceAccountVerifier::TOKEN]);

        self::assertSame(200, $this->responseStatus());
        self::assertSame(1, $backups->getLaunches());
        // Después de Neon y las copias, las aplicaciones con `latido: true` en
        // config/packages/suite.yaml, en el orden del catálogo.
        self::assertSame(['neon', 'copias', 'fichajes', 'tareas'], array_column($this->payload()['steps'], 'step'));
        self::assertSame(['step', 'outcome', 'detail'], array_keys($this->payload()['steps'][0]));
    }

    #[Test]
    public function aunque_falle_un_paso_contesta_200_para_que_cloud_scheduler_no_lo_repita_entero(): void
    {
        $backups = self::getContainer()->get(BackupLauncher::class);
        \assert($backups instanceof FakeBackupLauncher);
        $backups->failing();

        $this->request('POST', '/api/latido', server: ['HTTP_AUTHORIZATION' => 'Bearer '.FakeServiceAccountVerifier::TOKEN]);

        self::assertSame(200, $this->responseStatus());
        self::assertSame('fallo', $this->payload()['steps'][1]['outcome']);
    }

    #[Test]
    public function sin_token_es_un_401(): void
    {
        $this->request('POST', '/api/latido');

        self::assertSame(401, $this->responseStatus());
    }

    #[Test]
    public function ni_siquiera_quien_gestiona_los_permisos_puede_lanzarlo(): void
    {
        $this->given('admin@ampasainzvicuna.com', ['portal' => ['admin']]);
        $this->signedInAs('admin@ampasainzvicuna.com');

        $this->request('POST', '/api/latido', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor('admin@ampasainzvicuna.com')]);

        self::assertSame(401, $this->responseStatus());
    }
}
