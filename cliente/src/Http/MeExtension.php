<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Http;

use Ampa\PortalCliente\Security\PortalUser;

/**
 * Lo que una aplicación añade a `GET /api/me`, además de `name` y `email`.
 *
 * Por defecto nada (NoMeExtension). Fichajes, por ejemplo, añade `isEmployee`
 * e `isAdmin` para que su front sepa qué pantallas enseñar: lo sustituye con
 * el suyo en services.yaml.
 */
interface MeExtension
{
    /** @return array<string, mixed> campos que se añaden a la respuesta */
    public function describe(PortalUser $user): array;
}
