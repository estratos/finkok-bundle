<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\DependencyInjection;

use Finkok\CfdiBundle\Config\Credentials;
use Finkok\CfdiBundle\Config\CredentialsProvider;
use Finkok\CfdiBundle\Config\CredentialsProviderInterface;
use Finkok\CfdiBundle\Config\EndpointResolver;
use Finkok\CfdiBundle\Config\Environment;
use Finkok\CfdiBundle\Contract\CancelServiceInterface;
use Finkok\CfdiBundle\Contract\StampServiceInterface;
use Finkok\CfdiBundle\Csd\CsdEncoderInterface;
use Finkok\CfdiBundle\Csd\RawFileCsdEncoder;
use Finkok\CfdiBundle\DependencyInjection\Compiler\WiringPass;
use Finkok\CfdiBundle\Http\HttpClientFactory;
use Finkok\CfdiBundle\Service\CancelService;
use Finkok\CfdiBundle\Service\StampService;
use Finkok\CfdiBundle\Soap\HttpClientSoapTransport;
use Finkok\CfdiBundle\Soap\SoapTransportInterface;
use Finkok\CfdiBundle\Xml\CfdiPreflightValidator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
         *     default_profile: ?string,
         *     profiles: array<string, array{
         *         username: string,
         *         password: string,
         *         taxpayer_id: ?string,
         *         environment: string,
         *         certificate: ?string,
         *         private_key: ?string,
         *         private_key_passphrase: ?string,
         *         endpoints: array<string, array<string, ?string>>
         *     }>,
         *     endpoints: array<string, array<string, string>>,
         *     http: array{timeout: float, log_payloads: bool, user_agent: string, retry: array<string, mixed>},
         *     preflight: array{enabled: bool, require_signature: bool}
         * } $config
         */
        $config = $this->processConfiguration(new Configuration(), $configs);

        if ([] === $config['profiles']) {
            throw new InvalidConfigurationException(
                'Configura al menos un perfil bajo «finkok.profiles» con el usuario y la contraseña '
                .'que te proporcionó Finkok.',
            );
        }

        $this->registerCsdEncoder($container);
        $this->registerEndpointResolver($container, $config['endpoints']);
        $this->registerCredentials($container, $config['profiles'], $config['default_profile']);
        $this->registerPreflightValidator($container, $config['preflight']);
        $this->registerHttp($container, $config['http']);
        $this->registerTransport($container, $config['http']);
        $this->registerServices($container);
    }

    private function registerCsdEncoder(ContainerBuilder $container): void
    {
        $container->register('finkok.csd_encoder', RawFileCsdEncoder::class)
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
     * @param array<string, array<string, mixed>> $profiles
     */
    private function registerCredentials(ContainerBuilder $container, array $profiles, ?string $defaultProfile): void
    {
        $references = [];
        $usedIds = [];

        foreach ($profiles as $name => $profile) {
            $serviceId = $this->profileServiceId((string) $name, $usedIds);
            $usedIds[$serviceId] = true;

            $container->register($serviceId, Credentials::class)
                ->setArguments([
                    $name,
                    $profile['username'],
                    $profile['password'],
                    $profile['taxpayer_id'],
                    Environment::from($profile['environment']),
                    $profile['certificate'],
                    $profile['private_key'],
                    $profile['private_key_passphrase'],
                    $this->normalizeProfileEndpoints($profile['endpoints'] ?? []),
                    new Reference('finkok.csd_encoder'),
                ])
                ->setPublic(false);

            $references[$name] = new Reference($serviceId);
        }

        $container->register('finkok.credentials_provider', CredentialsProvider::class)
            ->setArguments([$references, $defaultProfile])
            ->setPublic(false);

        $container->setAlias(CredentialsProviderInterface::class, 'finkok.credentials_provider')
            ->setPublic(true);
    }

    /**
     * @param array<string, array<string, ?string>> $endpoints
     *
     * @return array<string, array<string, string>>
     */
    private function normalizeProfileEndpoints(array $endpoints): array
    {
        $normalized = [];

        foreach ($endpoints as $service => $environments) {
            foreach ($environments as $environment => $url) {
                if (null !== $url && '' !== trim($url)) {
                    $normalized[$service][$environment] = $url;
                }
            }
        }

        return $normalized;
    }

    /**
     * Genera un id de servicio estable y válido a partir del nombre del perfil.
     *
     * @param array<string, bool> $usedIds
     */
    private function profileServiceId(string $name, array $usedIds): string
    {
        $sanitized = (string) preg_replace('/[^A-Za-z0-9_.-]/', '_', $name);
        $serviceId = 'finkok.credentials.'.$sanitized;

        if ($serviceId === 'finkok.credentials.' || isset($usedIds[$serviceId])) {
            $serviceId .= '.'.substr(md5($name), 0, 6);
        }

        return $serviceId;
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
