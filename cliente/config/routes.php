<?php

declare(strict_types=1);

use Ampa\PortalCliente\Http\MeController;
use Ampa\PortalCliente\Http\SignOutController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Las dos rutas que pone el cliente en cada aplicación. Se importan desde
 * config/routes/ampa_portal_cliente.yaml de la aplicación:
 *
 *   ampa_portal_cliente:
 *       resource: '@AmpaPortalClienteBundle/config/routes.php'
 *
 * Son el contrato con el front (@ampa/ui): cambiarlas sube la segunda cifra.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('ampa_portal_cliente_me', '/api/me')
        ->controller(MeController::class)
        ->methods(['GET']);

    $routes->add('ampa_portal_cliente_sign_out', '/api/auth/salir')
        ->controller(SignOutController::class)
        ->methods(['POST']);
};
