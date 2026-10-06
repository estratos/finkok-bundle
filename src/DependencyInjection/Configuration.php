<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\DependencyInjection;

use Estratos\FinkokBundle\Config\Environment;
use Estratos\FinkokBundle\Config\Service;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Árbol de configuración del bundle (clave raíz `finkok`).
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('finkok');
        $root = $treeBuilder->getRootNode();

        $root
            ->info('Web Services de Finkok para timbrado y cancelación de CFDI.')
            ->children()
                // Las credenciales NO se configuran en este árbol.
                // Los perfiles los crea la aplicación que consume los servicios y se
                // inyectan mediante un servicio CredentialsProviderInterface: pueden
                // venir de variables de entorno, de la base de datos o del inquilino
                // activo, y nunca deben quedar embebidos en la configuración del bundle.
                ->arrayNode('endpoints')
                    ->info('URLs de los servicios. Los valores por defecto apuntan a los hosts oficiales de Finkok.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode(Service::Stamp->value)
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('demo')->defaultValue(Service::Stamp->defaultEndpoint(Environment::Demo))->end()
                                ->scalarNode('production')->defaultValue(Service::Stamp->defaultEndpoint(Environment::Production))->end()
                            ->end()
                        ->end()
                        ->arrayNode(Service::Cancel->value)
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('demo')->defaultValue(Service::Cancel->defaultEndpoint(Environment::Demo))->end()
                                ->scalarNode('production')->defaultValue(Service::Cancel->defaultEndpoint(Environment::Production))->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('http')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->floatNode('timeout')
                            ->defaultValue(30.0)
                            ->min(0)
                            ->info('Tiempo máximo, en segundos, de cada petición al Web Service.')
                        ->end()
                        ->booleanNode('log_payloads')
                            ->defaultFalse()
                            ->info('Escribe el envelope completo en el log. No lo actives en producción: contiene datos fiscales y CSD.')
                        ->end()
                        ->scalarNode('user_agent')
                            ->defaultValue('finkok-cfdi-bundle/1.0')
                        ->end()
                        ->arrayNode('retry')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultTrue()
                                    ->info('Reintenta errores de transporte y códigos transitorios. Es seguro porque Finkok deduplica por UUID.')
                                ->end()
                                ->integerNode('max_retries')->defaultValue(2)->min(0)->max(10)->end()
                                ->integerNode('delay_ms')->defaultValue(500)->min(0)->end()
                                ->floatNode('multiplier')->defaultValue(2.0)->min(1.0)->end()
                                ->integerNode('max_delay_ms')->defaultValue(0)->min(0)->end()
                                ->floatNode('jitter')->defaultValue(0.1)->min(0.0)->max(1.0)->end()
                                ->arrayNode('http_codes')
                                    ->info('Códigos que disparan reintento; 0 representa un error de transporte.')
                                    ->integerPrototype()->end()
                                    ->defaultValue([0, 429, 500, 502, 503, 504])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('preflight')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Valida el CFDI en local antes de enviarlo, evitando incidencias 301/705 y el límite de 1 MB.')
                        ->end()
                        ->booleanNode('require_signature')
                            ->defaultTrue()
                            ->info('Exige el atributo Sello en el comprobante; sin él Finkok responde CFDI40102.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
