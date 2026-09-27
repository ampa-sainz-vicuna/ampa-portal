<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Console;

use App\Portal\Application\Heartbeat\RunHeartbeat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * El latido diario a mano, lo mismo que hace Cloud Scheduler a las 4:00 con
 * POST /api/latido:
 *
 *   bin/console app:latido
 *
 * En desarrollo se salta casi todo (sin Neon, sin job de copias y sin
 * servidor de metadatos para despertar a nadie), pero enseña qué haría.
 */
#[AsCommand(name: 'app:latido', description: 'Hace el latido diario de la suite: Neon, copias y despertar a las aplicaciones.')]
final readonly class HeartbeatCommand
{
    public function __construct(private RunHeartbeat $heartbeat)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $report = ($this->heartbeat)();

        $io->table(['Paso', 'Resultado', 'Detalle'], array_map(
            static fn (array $step): array => [$step['step'], $step['outcome'], $step['detail']],
            $report->getSteps(),
        ));

        return $report->hasFailures() ? Command::FAILURE : Command::SUCCESS;
    }
}
