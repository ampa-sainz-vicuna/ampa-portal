<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * No se ha podido preguntar al portal (no responde, tarda demasiado o
 * contesta algo inesperado). No es culpa de quien hace la petición: la
 * aplicación responde 503 y no toca la cookie.
 */
final class PortalUnavailable extends \RuntimeException
{
}
