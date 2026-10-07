<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\DependencyInjection;

use Estratos\FinkokBundle\Config\EndpointResolver;
use Estratos\FinkokBundle\Contract\CancelServiceInterface;
use Estratos\FinkokBundle\Contract\StampServiceInterface;
use Estratos\FinkokBundle\Csd\CsdEncoderInterface;
use Estratos\FinkokBundle\Csd\PanelEncryptedCsdEncoder;
use Estratos\FinkokBundle\DependencyInjection\Compiler\WiringPass;
use Estratos\FinkokBundle\Http\HttpClientFactory;
use Estratos\FinkokBundle\Service\CancelService;
use Estratos\FinkokBundle\Service\StampService;
use Estratos\FinkokBundle\Soap\HttpClientSoapTransport;
use Estratos\FinkokBundle\Soap\SoapTransportInterface;
use Estratos\FinkokBundle\Xml\CfdiPreflightValidator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClientInterface;

/**
 * Registra los servicios del bundle a partir de la configuración `finkok`.
 */
final class FinkokExtension extends Extension
{
    public function getAlias(): string
    {
        return 'finkok';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{
         *     endpoints: array<string, array<string, string>>,
         *     http: array{timeout: float, log_payloads: bool, user_agent: string, retry: array<string, mixed>},
         *     preflight: array{enabled: bool, require_signature: bool}
         * } $config
         */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerCsdEncoder($container);
        $this->registerEndpointResolver($container, $config['endpoints']);
        $this->registerPreflightValidator($container, $config['preflight']);
        $this->registerHttp($container, $config['http']);
        $this->registerTransport($container, $config['http']);
        $this->registerServices($container);
    }

    private function registerCsdEncoder(ContainerBuilder $container): void
    {
        $container->register('finkok.csd_encoder', PanelEncryptedCsdEncoder::class)
            ->setPublic(false);

        // Alias de conveniencia para autowiring y para permitir decorar el
        // codificador sin tocar el id interno.
        $container->setAlias(CsdEncoderInterface::class, 'finkok.csd_encoder')
            ->setPublic(true);
    }

    /**
     * @param array<string, array<string, string>> $endpoints
     */
    private function registerEndpointResolver(ContainerBuilder $container, array $endpoints): void
    {
        $container->register('finkok.endpoint_resolver', EndpointResolver::class)
            ->setArguments([$endpoints])
            ->setPublic(false);
    }

    /**
     * @param array{enabled: bool, require_signature: bool} $preflight
     */
    private function registerPreflightValidator(ContainerBuilder $container, array $preflight): void
    {
        $container->register('finkok.preflight_validator', CfdiPreflightValidator::class)
            ->setArguments([$preflight['enabled'], $preflight['require_signature']])
            ->setPublic(false);
    }

    /**
     * @param array{timeout: float, log_payloads: bool, user_agent: string, retry: array<string, mixed>} $http
     */
    private function registerHttp(ContainerBuilder $container, array $http): void
    {
        $container->register('finkok.http_client_factory', HttpClientFactory::class)
            ->setArguments([
                // El cliente base lo inyecta WiringPass si la aplicación tiene el
                // servicio `http_client`; mientras tanto la fábrica crea uno.
                '$base' => null,
                '$options' => ['timeout' => $http['timeout']],
                '$retryEnabled' => (bool) ($http['retry']['enabled'] ?? true),
                '$maxRetries' => (int) ($http['retry']['max_retries'] ?? 2),
                '$retry' => $http['retry'],
            ])
            ->setPublic(false);

        $container->register('finkok.http_client', HttpClientInterface::class)
            ->setFactory([new Reference('finkok.http_client_factory'), 'create'])
            ->setPublic(false);
    }

    /**
     * @param array{timeout: float, log_payloads: bool, user_agent: string, retry: array<string, mixed>} $http
     */
    private function registerTransport(ContainerBuilder $container, array $http): void
    {
        $container->register('finkok.transport', HttpClientSoapTransport::class)
            ->setArguments([
                new Reference('finkok.http_client'),
                $http['timeout'],
                // `finkok.logger` siempre existe: WiringPass lo apunta al logger de
                // la aplicación o a un NullLogger.
                new Reference(WiringPass::LOGGER_SERVICE),
                $http['log_payloads'],
                $http['user_agent'],
            ])
            ->setPublic(false);

        $container->setAlias(SoapTransportInterface::class, 'finkok.transport')
            ->setPublic(true);
    }

    private function registerServices(ContainerBuilder $container): void
    {
        $container->register('finkok.stamp_service', StampService::class)
            ->setArguments([
                new Reference('finkok.transport'),
                new Reference('finkok.endpoint_resolver'),
                new Reference('finkok.credentials_provider'),
                new Reference('finkok.preflight_validator'),
                new Reference(WiringPass::LOGGER_SERVICE),
            ])
            ->setPublic(false);

        $container->setAlias(StampServiceInterface::class, 'finkok.stamp_service')
            ->setPublic(true);

        $container->register('finkok.cancel_service', CancelService::class)
            ->setArguments([
                new Reference('finkok.transport'),
                new Reference('finkok.endpoint_resolver'),
                new Reference('finkok.credentials_provider'),
                new Reference(WiringPass::LOGGER_SERVICE),
            ])
            ->setPublic(false);

        $container->setAlias(CancelServiceInterface::class, 'finkok.cancel_service')
            ->setPublic(true);
    }
}
