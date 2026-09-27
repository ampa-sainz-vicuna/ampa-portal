<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Event;

/**
 * El latido diario de la suite ha llegado: el portal llama a
 * `POST /api/latido` de cada aplicación que lo tenga activado
 * (`latido: true` en su suite.yaml), cada noche a las 4:00 hora de Madrid.
 * Desde la 0.1.5.
 *
 * Es el "cron" de la suite: lo que tiene que pasar aunque nadie abra la
 * aplicación ese día (en fichajes, cerrar el día y avisar de los días sin
 * fichar; en tareas, los vencimientos). No hay nadie detrás: para preguntar
 * al portal, SuiteMembers y SuiteRecipients usan solos el token del servidor.
 *
 * Se escucha con `#[AsEventListener]`. Debería llegar una vez al día, pero lo
 * que se enganche tiene que aguantar que llegue dos veces (alguien lo lanza a
 * mano) o ninguna (el portal no pudo): mejor "hacer lo que falte" que "hacer
 * lo de hoy". Si un oyente lanza una excepción, la ruta contesta 500 y el
 * portal lo registra como error (y llega en el correo de las alertas).
 */
final readonly class HeartbeatReceived
{
    public function __construct(private \DateTimeImmutable $receivedAt)
    {
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
