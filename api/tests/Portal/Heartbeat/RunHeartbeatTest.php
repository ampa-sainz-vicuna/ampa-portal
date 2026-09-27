<?php

declare(strict_types=1);

namespace App\Tests\Portal\Heartbeat;

use App\Portal\Application\Heartbeat\HeartbeatReport;
use App\Portal\Application\Heartbeat\RunHeartbeat;
use App\Portal\Domain\Suite\Application;
use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Tests\Double\FakeApplicationWaker;
use App\Tests\Double\FakeBackupLauncher;
use App\Tests\Double\FakeDatabaseUsage;
use App\Tests\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RunHeartbeatTest extends TestCase
{
    private FakeDatabaseUsage $neon;
    private FakeBackupLauncher $backups;
    private FakeApplicationWaker $waker;
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->neon = new FakeDatabaseUsage();
        $this->backups = new FakeBackupLauncher();
        $this->waker = new FakeApplicationWaker();
        $this->logger = new RecordingLogger();
    }

    #[Test]
    public function despierta_solo_a_las_aplicaciones_que_tienen_el_latido_instalado(): void
    {
        ($this->heartbeat())();

        // Ni listados (sin latido) ni el portal (sin dirección).
        self::assertSame(['fichajes', 'tareas'], $this->waker->getWoken());
    }

    #[Test]
    public function si_una_aplicacion_falla_despierta_igual_a_las_demas_y_lo_registra_como_error(): void
    {
        $this->waker->fails('fichajes');

        $report = ($this->heartbeat())();

        self::assertSame(['tareas'], $this->waker->getWoken());
        self::assertTrue($report->hasFailures());
        self::assertSame(HeartbeatReport::FAILED, $this->step($report, 'fichajes')['outcome']);
        self::assertStringContainsString('fichajes', $this->logger->errors()[0]);
    }

    #[Test]
    public function sin_configurar_se_salta_neon_y_las_copias_sin_que_sea_un_fallo(): void
    {
        $report = ($this->heartbeat())();

        self::assertSame(HeartbeatReport::SKIPPED, $this->step($report, 'neon')['outcome']);
        self::assertSame(HeartbeatReport::SKIPPED, $this->step($report, 'copias')['outcome']);
        self::assertFalse($report->hasFailures());
        self::assertSame([], $this->logger->errors());
    }

    #[Test]
    public function lanza_las_copias_una_vez(): void
    {
        $this->backups->configured();

        $report = ($this->heartbeat())();

        self::assertSame(1, $this->backups->getLaunches());
        self::assertSame('lanzadas: ampa-copias-1', $this->step($report, 'copias')['detail']);
    }

    #[Test]
    public function si_no_se_pueden_lanzar_las_copias_lo_registra_como_error_y_sigue(): void
    {
        $this->backups->failing();

        $report = ($this->heartbeat())();

        self::assertSame(HeartbeatReport::FAILED, $this->step($report, 'copias')['outcome']);
        self::assertSame(['fichajes', 'tareas'], $this->waker->getWoken());
        self::assertCount(1, $this->logger->errors());
    }

    #[Test]
    public function con_poco_computo_gastado_no_avisa(): void
    {
        $this->neon->uses(42.5);

        $report = ($this->heartbeat())();

        self::assertSame(HeartbeatReport::DONE, $this->step($report, 'neon')['outcome']);
        self::assertSame('42,5 de 100 CU-horas', $this->step($report, 'neon')['detail']);
        self::assertSame([], $this->logger->errors());
    }

    #[Test]
    public function al_pasar_del_80_por_ciento_del_computo_avisa_con_un_error_que_se_entiende_solo(): void
    {
        $this->neon->uses(80);

        ($this->heartbeat())();

        self::assertCount(1, $this->logger->errors());
        self::assertStringContainsString('80 % del cómputo gratuito', $this->logger->errors()[0]);
        self::assertStringContainsString('todas las bases se paran', $this->logger->errors()[0]);
    }

    #[Test]
    public function si_neon_no_contesta_lo_registra_y_sigue(): void
    {
        $this->neon->isDown();

        $report = ($this->heartbeat())();

        self::assertSame(HeartbeatReport::FAILED, $this->step($report, 'neon')['outcome']);
        self::assertSame(['fichajes', 'tareas'], $this->waker->getWoken());
    }

    private function heartbeat(): RunHeartbeat
    {
        return new RunHeartbeat(
            new ApplicationCatalog([
                new Application('fichajes', 'Fichajes', 'https://empleados.ejemplo', ['admin' => 'Admin'], heartbeat: true),
                new Application('listados', 'Listados', 'https://listados.ejemplo', ['usuario' => 'Usuario']),
                new Application('tareas', 'Tareas', 'https://tareas.ejemplo', ['miembro' => 'Miembro'], heartbeat: true),
                new Application('portal', 'Portal', '', ['admin' => 'Admin'], heartbeat: true),
            ]),
            $this->neon,
            $this->backups,
            $this->waker,
            $this->logger,
        );
    }

    /** @return array{step: string, outcome: string, detail: string} */
    private function step(HeartbeatReport $report, string $step): array
    {
        foreach ($report->getSteps() as $candidate) {
            if ($step === $candidate['step']) {
                return $candidate;
            }
        }

        self::fail(sprintf('El latido no tiene el paso "%s".', $step));
    }
}
