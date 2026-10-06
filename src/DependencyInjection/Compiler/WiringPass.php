<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\DependencyInjection\Compiler;

use Estratos\FinkokBundle\Config\CredentialsProviderInterface;
use Estratos\FinkokBundle\Config\UnconfiguredCredentialsProvider;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Completa el cableado del bundle en tiempo de compilación.
 *
 * Se hace en un compiler pass —y no al cargar la extensión— porque el orden de
 * carga de bundles no está garantizado: si este bundle se registra antes que
 * FrameworkBundle, los servicios http_client y logger todavía no existen.
 *
 * Además, deliberadamente no se usa IGNORE_ON_INVALID_REFERENCE dentro de
 * arreglos de argumentos: cuando la referencia no resuelve, Symfony elimina la
 * entrada del arreglo y desplaza las posiciones, lo que rompería los argumentos
 * posicionales de los constructores.
 */
final class WiringPass implements CompilerPassInterface
{
    /** Id interno que consumen los servicios del bundle. */
    public const CREDENTIALS_SERVICE = 'finkok.credentials_provider';

    public const UNCONFIGURED_CREDENTIALS_SERVICE = 'finkok.unconfigured_credentials_provider';

    public const FACTORY_SERVICE = 'finkok.http_client_factory';

    public const LOGGER_SERVICE = 'finkok.logger';

    public const NULL_LOGGER_SERVICE = 'finkok.null_logger';

    public function process(ContainerBuilder $container): void
    {
        $this->wireLogger($container);
        $this->wireHttpClient($container);
        $this->wireCredentialsProvider($container);
    }

    /**
     * Expone siempre un servicio finkok.logger: el de la aplicación si existe,
     * o un NullLogger en caso contrario.
     */
    private function wireLogger(ContainerBuilder $container): void
    {
        if ($container->has('logger')) {
            $container->setAlias(self::LOGGER_SERVICE, 'logger')->setPublic(false);

            return;
        }

        $container->register(self::NULL_LOGGER_SERVICE, NullLogger::class)->setPublic(false);
        $container->setAlias(self::LOGGER_SERVICE, self::NULL_LOGGER_SERVICE)->setPublic(false);
    }

    /**
     * Reutiliza el cliente HTTP de la aplicación para compartir pool de
     * conexiones, proxy y CA configurados; si no existe, la fábrica crea uno.
     */
    private function wireHttpClient(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::FACTORY_SERVICE)) {
            return;
        }

        $container->getDefinition(self::FACTORY_SERVICE)->setArgument(
            '$base',
            $container->has('http_client') ? new Reference('http_client') : null,
        );
    }

    /**
     * Conecta el proveedor de credenciales que define la aplicación.
     *
     * Los perfiles no viven en la configuración del bundle: la aplicación registra
     * un servicio que implemente CredentialsProviderInterface y aquí se enlaza con
     * los servicios de timbrado y cancelación.
     *
     * Si la aplicación no lo registra, se usa un proveedor que falla con un mensaje
     * accionable en cuanto se intente operar. Se prefiere eso a fallar en tiempo de
     * compilación, porque hay aplicaciones que instalan el bundle sin usar Finkok
     * (por ejemplo el panel de administración de un sistema multi-app) y su
     * contenedor no debe romperse por ello.
     */
    private function wireCredentialsProvider(ContainerBuilder $container): void
    {
        $appDefined = $container->hasDefinition(CredentialsProviderInterface::class)
            || $container->hasAlias(CredentialsProviderInterface::class);

        if ($appDefined) {
            $container->setAlias(self::CREDENTIALS_SERVICE, CredentialsProviderInterface::class)
                ->setPublic(false);

            return;
        }

        $container->register(self::UNCONFIGURED_CREDENTIALS_SERVICE, UnconfiguredCredentialsProvider::class)
            ->setPublic(false);

        $container->setAlias(self::CREDENTIALS_SERVICE, self::UNCONFIGURED_CREDENTIALS_SERVICE)
            ->setPublic(false);

        // También se expone la interfaz para que el autowiring falle con el mismo
        // mensaje en lugar de con un «service not found» de Symfony.
        $container->setAlias(CredentialsProviderInterface::class, self::UNCONFIGURED_CREDENTIALS_SERVICE)
            ->setPublic(true);
    }
}
