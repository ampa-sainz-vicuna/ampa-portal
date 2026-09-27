<?php

declare(strict_types=1);

namespace App\Portal\Application\Heartbeat;

/**
 * Lanza las copias de seguridad de todas las bases de la suite. No espera a
 * que acaben: el job corre por su cuenta y, si falla, lo dice él en sus
 * registros (y la alerta lo recoge).
 */
interface BackupLauncher
{
    /** Sin configurar (desarrollo), el latido se lo salta. */
    public function isConfigured(): bool;

    /**
     * @return string cómo se llama la ejecución, para buscarla en la consola
     *
     * @throws HeartbeatFailed
     */
    public function launch(): string;
}
