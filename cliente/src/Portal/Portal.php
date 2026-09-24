<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

/**
 * Las dos preguntas que una aplicación le hace al portal, siempre con el token
 * de quien está haciendo la petición.
 *
 * Es una interfaz para que los tests de cada aplicación no necesiten un portal
 * de verdad: en `when@test` se cambia por `Ampa\PortalCliente\Testing\FakePortal`
 * (README, "Tests de la aplicación").
 */
interface Portal
{
    /**
     * Quién es y qué roles tiene en ESTA aplicación.
     *
     * @throws SessionRejected   si el token no vale o la persona está desactivada
     * @throws PortalUnavailable si no se ha podido preguntar
     */
    public function access(string $token): PortalAccess;

    /**
     * A quién avisar: quienes tienen ese rol en esta aplicación, cada uno con
     * los correos que haya elegido. Solo contesta si quien pregunta tiene algún
     * rol aquí.
     *
     * @return list<Recipient>
     *
     * @throws SessionRejected   si el token no vale o no tiene acceso a esta aplicación
     * @throws PortalUnavailable si no se ha podido preguntar
     */
    public function recipients(string $token, string $role): array;
}
