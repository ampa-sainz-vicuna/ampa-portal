<?php

declare(strict_types=1);

namespace App\Portal\Application\Heartbeat;

use App\Portal\Domain\Suite\ApplicationCatalog;
use Psr\Log\LoggerInterface;

/**
 * El latido diario de la suite: lo que haría un cron, con UN solo trabajo de
 * Cloud Scheduler para todas las aplicaciones (hay 3 gratis por cuenta de
 * facturación y tareas ya gasta uno).
 *
 * Cloud Scheduler llama a POST /api/latido del portal a las 4:00 (Madrid) y
 * el portal, por orden:
 *
 * 1. Mira cuánto cómputo de Neon lleva gastado la suite este mes. Neon
 *    gratuito da 100 CU-horas al mes para todo el proyecto `ampa`; al
 *    acabarse, **todas** las bases se paran hasta el mes siguiente.
 * 2. Lanza las copias de seguridad de todas las bases (el job de Cloud Run
 *    `ampa-copias`, deploy/copias/).
 * 3. Despierta, una por una, a las aplicaciones que lo tienen instalado
 *    (`latido: true` en suite.yaml; cliente 0.1.5), que hacen su trabajo
 *    programado.
 *
 * Cada paso va por su lado: si uno falla, los demás se hacen igual. Los
 * fallos se registran como ERROR, que es lo que vigila la alerta de Cloud
 * Monitoring (deploy/alertas.sh) y lo que acaba en un correo. Por eso el
 * latido no falla nunca: si devolviera un error, Cloud Scheduler lo
 * reintentaría entero y lanzaría las copias dos veces.
 */
final readonly class RunHeartbeat
{
    /** A partir de qué parte del cómputo gratuito se avisa. */
    public const float COMPUTE_WARNING_SHARE = 0.8;

    public function __construct(
        private ApplicationCatalog $catalog,
        private DatabaseUsage $databaseUsage,
        private BackupLauncher $backups,
        private ApplicationWaker $waker,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(): HeartbeatReport
    {
        $report = new HeartbeatReport();

        $this->checkDatabaseUsage($report);
        $this->launchBackups($report);

        foreach ($this->catalog->all() as $application) {
            if (!$application->receivesHeartbeat()) {
                continue;
            }

            try {
                $this->waker->wake($application);
                $report->done($application->getCode(), 'despertada');
            } catch (HeartbeatFailed $e) {
                $this->logger->error('Latido: {application} no ha contestado bien ({error}).', [
                    'application' => $application->getCode(),
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
                $report->failed($application->getCode(), $e->getMessage());
            }
        }

        return $report;
    }

    private function checkDatabaseUsage(HeartbeatReport $report): void
    {
        if (!$this->databaseUsage->isConfigured()) {
            $report->skipped('neon', 'sin configurar (NEON_PROJECT_ID, NEON_API_KEY)');

            return;
        }

        try {
            $usage = $this->databaseUsage->current();
        } catch (HeartbeatFailed $e) {
            $this->logger->error('Latido: no se ha podido preguntar a Neon cuánto cómputo va gastado ({error}).', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $report->failed('neon', $e->getMessage());

            return;
        }

        $summary = $usage->describe();

        if ($usage->share() >= self::COMPUTE_WARNING_SHARE) {
            // El mensaje es lo que llega en el correo de la alerta: que se
            // entienda solo.
            $this->logger->error('Neon: la suite lleva gastado el {percent} % del cómputo gratuito del mes ({summary}). Si llega al 100 %, todas las bases se paran hasta el mes que viene.', [
                'percent' => (int) floor($usage->share() * 100),
                'summary' => $summary,
            ]);
            $report->failed('neon', $summary);

            return;
        }

        $this->logger->info('Neon: {summary}.', ['summary' => $summary]);
        $report->done('neon', $summary);
    }

    private function launchBackups(HeartbeatReport $report): void
    {
        if (!$this->backups->isConfigured()) {
            $report->skipped('copias', 'sin configurar (COPIAS_JOB)');

            return;
        }

        try {
            $report->done('copias', 'lanzadas: '.$this->backups->launch());
        } catch (HeartbeatFailed $e) {
            $this->logger->error('Latido: no se han podido lanzar las copias de seguridad ({error}).', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $report->failed('copias', $e->getMessage());
        }
    }
}
