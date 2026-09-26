<?php

declare(strict_types=1);

use Ampa\PortalCliente\Http\MeController;
use Ampa\PortalCliente\Http\MeExtension;
use Ampa\PortalCliente\Http\NoMeExtension;
use Ampa\PortalCliente\Http\SignOutController;
use Ampa\PortalCliente\Portal\ApplicationIdentity;
use Ampa\PortalCliente\Portal\CallerToken;
use Ampa\PortalCliente\Portal\HttpPortal;
use Ampa\PortalCliente\Portal\MetadataServerIdentity;
use Ampa\PortalCliente\Portal\Portal;
use Ampa\PortalCliente\Portal\SuiteMembers;
use Ampa\PortalCliente\Portal\SuiteRecipients;
use Ampa\PortalCliente\Security\ApplicationRoles;
use Ampa\PortalCliente\Security\CrossSiteRequestGuard;
use Ampa\PortalCliente\Security\JsonAccessDeniedHandler;
use Ampa\PortalCliente\Security\PortalAuthenticator;
use Ampa\PortalCliente\Security\PortalRolesAsSymfonyRoles;
use Ampa\PortalCliente\Security\PortalUserProvider;
use Ampa\PortalCliente\Security\SuiteCookie;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelEvents;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Los servicios del cliente. Los que una aplicación puede sustituir en su
 * services.yaml son los dos puntos de extensión (ApplicationRoles y
 * MeExtension) y, en los tests, Portal (por FakePortal).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(HttpPortal::class)
        ->args([
            service('http_client'),
            param('ampa_portal_cliente.portal_url'),
            param('ampa_portal_cliente.aplicacion'),
        ]);
    $services->alias(Portal::class, HttpPortal::class);

    // El token de este servidor, para preguntar sin nadie detrás (0.1.3).
    $services->set(MetadataServerIdentity::class)
        ->args([
            service('http_client'),
            param('ampa_portal_cliente.portal_url'),
        ]);
    $services->alias(ApplicationIdentity::class, MetadataServerIdentity::class);
    $services->set(CallerToken::class);

    $services->set(SuiteCookie::class)
        ->args([
            param('ampa_portal_cliente.cookie_domain'),
            param('ampa_portal_cliente.cookie_secure'),
        ]);

    $services->set(PortalRolesAsSymfonyRoles::class);
    $services->alias(ApplicationRoles::class, PortalRolesAsSymfonyRoles::class);

    $services->set(NoMeExtension::class);
    $services->alias(MeExtension::class, NoMeExtension::class);

    $services->set(PortalAuthenticator::class);
    $services->set(PortalUserProvider::class);
    $services->set(JsonAccessDeniedHandler::class);
    $services->set(SuiteRecipients::class);
    $services->set(SuiteMembers::class);

    $services->set(CrossSiteRequestGuard::class)
        // Prioridad alta: antes que el cortafuegos, para no preguntar al
        // portal por una petición que se va a rechazar igualmente.
        ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'priority' => 256]);

    $services->set(MeController::class)->public()->tag('controller.service_arguments');
    $services->set(SignOutController::class)->public()->tag('controller.service_arguments');
};
