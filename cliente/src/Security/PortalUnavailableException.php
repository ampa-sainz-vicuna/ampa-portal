<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * El portal no ha contestado. Un 503 y la cookie intacta: la sesión puede
 * estar perfectamente bien, y en cuanto el portal vuelva, sigue valiendo.
 */
final class PortalUnavailableException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'No se ha podido comprobar la sesión. Prueba otra vez en un momento.';
    }
}
