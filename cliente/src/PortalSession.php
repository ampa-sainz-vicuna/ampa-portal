<?php

declare(strict_types=1);

namespace Ampa\PortalCliente;

/**
 * Lo que el cliente comparte con el portal sin llamarlo: el nombre de la
 * cookie de sesión de la suite.
 *
 * Tiene que coincidir con `App\Portal\Infrastructure\Security\SessionCookie::NAME`
 * del portal. Cambiarlo exige cambiar los dos y subir la segunda cifra de la
 * versión.
 */
final class PortalSession
{
    public const string COOKIE = 'ampa_sesion';
}
