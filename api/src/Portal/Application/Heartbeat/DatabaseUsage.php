<?php

declare(strict_types=1);

namespace App\Portal\Application\Heartbeat;

/** Cuánto cómputo lleva gastado este mes el proyecto de Neon de la suite. */
interface DatabaseUsage
{
    /** Sin configurar (desarrollo, o sin clave de la API de Neon), el latido se lo salta. */
    public function isConfigured(): bool;

    /**
     * @throws HeartbeatFailed
     */
    public function current(): ComputeUsage;
}
