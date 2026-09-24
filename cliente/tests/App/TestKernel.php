<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Tests\App;

use Ampa\PortalCliente\AmpaPortalClienteBundle;
use Ampa\PortalCliente\Portal\Portal;
use Ampa\PortalCliente\Portal\SuiteRecipients;
use Ampa\PortalCliente\Security\JsonAccessDeniedHandler;
use Ampa\PortalCliente\Security\PortalAuthenticator;
use Ampa\PortalCliente\Security\PortalUserProvider;
use Ampa\PortalCliente\Testing\FakePortal;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Una aplicación mínima que instala el cliente igual que lo hará cualquiera
 * de la suite (README, "Instalarlo en una aplicación"). Si estos tests pasan,
 * esas instrucciones funcionan.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new AmpaPortalClienteBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'tests',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        $container->extension('ampa_portal_cliente', [
            'aplicacion' => 'listados',
            'portal_url' => 'http://portal.invalid',
            'cookie_domain' => '.ampasainzvicuna.com',
            'cookie_secure' => true,
        ]);

        // Lo que pone cada aplicación en su security.yaml.
        $container->extension('security', [
            'providers' => ['portal' => ['id' => PortalUserProvider::class]],
            'firewalls' => [
                'api' => [
                    'pattern' => '^/api',
                    'stateless' => true,
                    'provider' => 'portal',
                    'custom_authenticators' => [PortalAuthenticator::class],
                    'entry_point' => PortalAuthenticator::class,
                    'access_denied_handler' => JsonAccessDeniedHandler::class,
                ],
            ],
            'access_control' => [
                ['path' => '^/api/auth/salir$', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/api/admin', 'roles' => 'ROLE_ADMIN'],
                ['path' => '^/api', 'roles' => 'IS_AUTHENTICATED'],
            ],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();

        // Lo que pone cada aplicación en el when@test de su services.yaml.
        $services->set(Portal::class, FakePortal::class);

        $services->set(PrivateController::class)->public()->tag('controller.service_arguments');
        $services->set(OpenedListener::class)->public();

        // Nadie lo inyecta en esta aplicación de pruebas, y Symfony lo
        // quitaría al compilar; el test lo pide al contenedor.
        $services->alias('test.suite_recipients', SuiteRecipients::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@AmpaPortalClienteBundle/config/routes.php');

        $routes->add('privado', '/api/privado')->controller([PrivateController::class, 'private']);
        $routes->add('admin', '/api/admin/algo')->controller([PrivateController::class, 'private']);
        $routes->add('guardar', '/api/guardar')->controller([PrivateController::class, 'private'])->methods(['POST']);
    }
}
