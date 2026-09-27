<?php

declare(strict_types=1);

use Ampa\PortalCliente\Http\HeartbeatController;
use Ampa\PortalCliente\Http\MeController;
use Ampa\PortalCliente\Http\SignOutController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Las rutas que pone el cliente en cada aplicación: /api/me y /api/auth/salir
 * (del front) y, desde la 0.1.5, /api/latido (del portal). Se importan desde
 * config/routes/ampa_portal_cliente.yaml de la aplicación:
 *
 *   ampa_portal_cliente:
 *       resource: '@AmpaPortalClienteBundle/config/routes.php'
 *
 * Son el contrato con el front (@ampa/ui) y con el portal: cambiarlas sube
 * la segunda cifra.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('ampa_portal_cliente_me', '/api/me')
        ->controller(MeController::class)
        ->methods(['GET']);

    $routes->add('ampa_portal_cliente_sign_out', '/api/auth/salir')
        ->controller(SignOutController::class)
        ->methods(['POST']);

    $routes->add('ampa_portal_cliente_heartbeat', '/api/latido')
        ->controller(HeartbeatController::class)
        ->methods(['POST']);
};
