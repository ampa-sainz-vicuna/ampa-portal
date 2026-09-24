<?php

declare(strict_types=1);

namespace Ampa\PortalCliente;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * El cliente del portal del AMPA para las aplicaciones Symfony de la suite.
 *
 * Configuración (config/packages/ampa_portal_cliente.yaml de la aplicación):
 *
 *   ampa_portal_cliente:
 *       aplicacion: listados                           # su código en el catálogo del portal
 *       portal_url: '%env(PORTAL_URL)%'                # el portal visto desde este servidor
 *       cookie_domain: '%env(SESSION_COOKIE_DOMAIN)%'  # el mismo que el del portal
 *       cookie_secure: '%env(bool:SESSION_COOKIE_SECURE)%'
 *
 * Qué más hay que tocar en la aplicación (security.yaml, rutas, tests), en
 * cliente/README.md.
 */
final class AmpaPortalClienteBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('aplicacion')
                    ->info('El código de esta aplicación en el catálogo del portal (fichajes, listados…).')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('portal_url')
                    ->info('Dónde está el portal visto desde este servidor, sin barra final.')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('cookie_domain')
                    ->info('El dominio de la cookie de sesión: el mismo que usa el portal. Vacío en desarrollo.')
                    ->defaultValue('')
                ->end()
                ->booleanNode('cookie_secure')
                    ->info('Si la cookie solo viaja por HTTPS: sí en producción.')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    /**
     * @param array{aplicacion: string, portal_url: string, cookie_domain: string, cookie_secure: bool} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('ampa_portal_cliente.aplicacion', $config['aplicacion'])
            ->set('ampa_portal_cliente.portal_url', $config['portal_url'])
            ->set('ampa_portal_cliente.cookie_domain', $config['cookie_domain'])
            ->set('ampa_portal_cliente.cookie_secure', $config['cookie_secure']);

        $container->import('../config/services.php');
    }
}
