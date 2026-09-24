<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * El portal no acepta la sesión: el token no vale, ha caducado o la persona
 * está desactivada. Hay que volver a entrar.
 */
final class SessionRejected extends \RuntimeException
{
}
