<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Ha entrado en la suite pero no tiene nada que hacer en esta aplicación. Es
 * un 403 y no un 401: la sesión vale, y mandarle a entrar otra vez no
 * arreglaría nada (daría vueltas entre el portal y la aplicación).
 */
final class NoAccessToApplication extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'No tienes acceso a esta aplicación.';
    }
}
