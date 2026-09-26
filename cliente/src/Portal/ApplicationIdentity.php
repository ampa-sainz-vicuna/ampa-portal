<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * El token con el que ESTE servidor pregunta al portal cuando no hay nadie
 * detrás (desde la 0.1.3): una tarea programada, como el resumen diario de
 * tareas a las 5:00, no trae la cookie de ninguna persona.
 *
 * Solo lo aceptan /api/personas y /api/avisos; /api/acceso sigue siendo cosa
 * de personas. En los tests de la aplicación se cambia por
 * `Ampa\PortalCliente\Testing\FakeApplicationIdentity`.
 */
interface ApplicationIdentity
{
    /**
     * @throws PortalUnavailable si no se ha podido conseguir (fuera de Google
     *                           Cloud, por ejemplo en desarrollo)
     */
    public function token(): string;
}
