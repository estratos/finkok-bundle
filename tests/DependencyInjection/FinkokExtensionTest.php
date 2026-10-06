<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\DependencyInjection;

use Estratos\FinkokBundle\Config\Credentials;
use Estratos\FinkokBundle\Config\CredentialsProvider;
use Estratos\FinkokBundle\Config\CredentialsProviderInterface;
use Estratos\FinkokBundle\Config\Environment;
use Estratos\FinkokBundle\Config\UnconfiguredCredentialsProvider;
use Estratos\FinkokBundle\Contract\CancelServiceInterface;
use Estratos\FinkokBundle\Contract\StampServiceInterface;
use Estratos\FinkokBundle\Csd\CsdEncoderInterface;
use Estratos\FinkokBundle\DependencyInjection\Compiler\WiringPass;
use Estratos\FinkokBundle\DependencyInjection\Configuration;
use Estratos\FinkokBundle\DependencyInjection\FinkokExtension;
use Estratos\FinkokBundle\Exception\ConfigurationException;
use Estratos\FinkokBundle\Service\CancelService;
use Estratos\FinkokBundle\Service\StampService;
use Estratos\FinkokBundle\Soap\SoapTransportInterface;
use Estratos\FinkokBundle\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * El bundle no lee credenciales de su configuración: los perfiles los crea la
 * aplicación y se inyectan como un servicio CredentialsProviderInterface.
 */
final class FinkokExtensionTest extends TestCase
{
    /**
     * Carga la extensión y ejecuta el compiler pass sin compilar.
     *
     * Se usa para inspeccionar las definiciones tal como las crea la extensión: al
     * compilar, Symfony inyecta los servicios privados en los públicos y los
     * renombra, de modo que los ids internos dejan de existir.
     *
     * @param array<string, mixed> $config
     */
    private function load(array $config = [], ?ContainerBuilder $container = null): ContainerBuilder
    {
        $container ??= new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        (new FinkokExtension())->load([$config], $container);
        (new WiringPass())->process($container);

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function compile(array $config = [], ?ContainerBuilder $container = null): ContainerBuilder
    {
        $container = $this->load($config, $container);
        $container->compile();

        return $container;
    }

    /**
     * Registra los perfiles como lo haría la aplicación que consume los servicios.
     */
    private function registerAppProfiles(ContainerBuilder $container): void
    {
        $container->register('app.finkok.credentials.matriz', Credentials::class)
            ->setArguments(['matriz', 'usuario@demo.com', 'clave-secreta', 'EKU9003173C9', Environment::Demo]);

        $container->register('app.finkok.credentials.sucursal', Credentials::class)
            ->setArguments(['sucursal', 'sucursal@demo.com', 'otra-clave', 'MISC491214B86', Environment::Production]);
    }

    /**
     * Registra el proveedor de credenciales que la aplicación expone al bundle.
     */
    private function registerAppCredentials(ContainerBuilder $container): void
    {
        $this->registerAppProfiles($container);

        $container->register(CredentialsProviderInterface::class, CredentialsProvider::class)
            ->setArguments([
                [
                    'matriz' => new Reference('app.finkok.credentials.matriz'),
                    'sucursal' => new Reference('app.finkok.credentials.sucursal'),
                ],
                'matriz',
            ])
            ->setPublic(true);
    }

    public function testLaConfiguracionDelBundleNoAdmiteCredenciales(): void
    {
        $children = array_keys((new Configuration())->getConfigTreeBuilder()->buildTree()->getChildren());
        sort($children);

        self::assertSame(['endpoints', 'http', 'preflight'], $children);
        self::assertNotContains('profiles', $children);
        self::assertNotContains('default_profile', $children);
    }

    public function testCompilaSinNingunaConfiguracionYExponeLosServicios(): void
    {
        $container = $this->compile();

        self::assertInstanceOf(StampService::class, $container->get(StampServiceInterface::class));
        self::assertInstanceOf(CancelService::class, $container->get(CancelServiceInterface::class));
        self::assertInstanceOf(SoapTransportInterface::class, $container->get(SoapTransportInterface::class));
        self::assertInstanceOf(CsdEncoderInterface::class, $container->get(CsdEncoderInterface::class));
    }

    public function testSinProveedorRegistradoElContenedorCompilaPeroFallaAlUsarse(): void
    {
        $container = $this->compile();

        $provider = $container->get(CredentialsProviderInterface::class);

        self::assertInstanceOf(UnconfiguredCredentialsProvider::class, $provider);
        self::assertSame([], $provider->names());
        self::assertFalse($provider->has('matriz'));

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/CredentialsProviderInterface/');
        $provider->get();
    }

    public function testElServicioDeTimbradoPropagaElMensajeCuandoNoHayCredenciales(): void
    {
        $container = $this->compile();
        $stamp = $container->get(StampServiceInterface::class);

        try {
            $stamp->stamp(Fixtures::signedCfdi());
            self::fail('Se esperaba ConfigurationException.');
        } catch (ConfigurationException $exception) {
            self::assertStringContainsString('no ha registrado un proveedor de credenciales', $exception->getMessage());
            self::assertStringContainsString('README', $exception->getMessage());
        }
    }

    public function testUsaElProveedorDeCredencialesDeLaAplicacion(): void
    {
        $container = new ContainerBuilder();
        $this->registerAppCredentials($container);
        $container = $this->compile([], $container);

        $provider = $container->get(CredentialsProviderInterface::class);

        self::assertSame(['matriz', 'sucursal'], $provider->names());
        self::assertSame('matriz', $provider->default()->name());
        self::assertSame(Environment::Production, $provider->get('sucursal')->environment());
    }

    public function testElProveedorDeLaAplicacionLlegaHastaLaPeticionHttp(): void
    {
        $recorded = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = ['url' => $url, 'body' => (string) ($options['body'] ?? '')];

            return new MockResponse(Fixtures::response('stamp-success'));
        });

        $container = new ContainerBuilder();
        $this->registerAppCredentials($container);
        $container->register('http_client', MockHttpClient::class)->setSynthetic(true)->setPublic(true);
        $container = $this->compile([], $container);
        $container->set('http_client', $client);

        $receipt = $container->get(StampServiceInterface::class)->stamp(Fixtures::signedCfdi());

        self::assertTrue($receipt->isSuccess());
        self::assertSame('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', $receipt->uuid);
        self::assertCount(1, $recorded);
        self::assertSame('https://demo-facturacion.finkok.com/servicios/soap/stamp', $recorded[0]['url']);
        self::assertStringContainsString('<tns:username>usuario@demo.com</tns:username>', $recorded[0]['body']);
    }

    public function testAceptaUnAliasDeLaAplicacionComoProveedor(): void
    {
        $container = new ContainerBuilder();
        $this->registerAppProfiles($container);
        $container->register('app.credentials', CredentialsProvider::class)
            ->setArguments([['matriz' => new Reference('app.finkok.credentials.matriz')], 'matriz']);
        $container->setAlias(CredentialsProviderInterface::class, 'app.credentials')->setPublic(true);

        $container = $this->compile([], $container);

        self::assertSame('matriz', $container->get(CredentialsProviderInterface::class)->default()->name());
    }

    public function testConectaElClienteHttpDeLaAplicacionCuandoExiste(): void
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class)->setPublic(true);

        $factory = $this->load([], $container)->getDefinition('finkok.http_client_factory');
        $base = $factory->getArgument('$base');

        self::assertInstanceOf(Reference::class, $base);
        self::assertSame('http_client', (string) $base);
    }

    public function testAplicaLosValoresPorDefectoDeLaInfraestructura(): void
    {
        $factory = $this->load()->getDefinition('finkok.http_client_factory');

        self::assertSame(30.0, $factory->getArgument('$options')['timeout']);
        self::assertTrue($factory->getArgument('$retryEnabled'));
        self::assertSame(2, $factory->getArgument('$maxRetries'));
        self::assertNull(
            $factory->getArgument('$base'),
            'Sin el servicio http_client en la aplicación, la fábrica crea un cliente propio.',
        );
    }

    public function testRegistraUnLoggerNuloCuandoLaAplicacionNoTieneLogger(): void
    {
        $container = $this->load();

        self::assertTrue($container->hasDefinition(WiringPass::NULL_LOGGER_SERVICE));
        self::assertSame(WiringPass::NULL_LOGGER_SERVICE, (string) $container->getAlias(WiringPass::LOGGER_SERVICE));
    }

    public function testUsaElLoggerDeLaAplicacionCuandoExiste(): void
    {
        $container = new ContainerBuilder();
        $container->register('logger', \Psr\Log\NullLogger::class)->setPublic(true);

        $container = $this->load([], $container);

        self::assertSame('logger', (string) $container->getAlias(WiringPass::LOGGER_SERVICE));
    }

    public function testPermiteSobrescribirEndpointsTimeoutYValidacionPrevia(): void
    {
        $container = $this->load([
            'endpoints' => ['stamp' => ['demo' => 'https://proxy.test/stamp']],
            'http' => ['timeout' => 12.5],
            'preflight' => ['enabled' => false],
        ]);

        $endpoints = $container->getDefinition('finkok.endpoint_resolver')->getArgument(0);

        self::assertSame('https://proxy.test/stamp', $endpoints['stamp']['demo']);
        self::assertSame(
            'https://facturacion.finkok.com/servicios/soap/stamp',
            $endpoints['stamp']['production'],
            'Los valores no sobrescritos conservan el endpoint oficial.',
        );
        self::assertSame(12.5, $container->getDefinition('finkok.http_client_factory')->getArgument('$options')['timeout']);
        self::assertFalse($container->getDefinition('finkok.preflight_validator')->getArgument(0));
    }

    public function testConfiguraElTimeoutDelTransporteYElUserAgent(): void
    {
        $arguments = $this->load([
            'http' => ['timeout' => 7.5, 'user_agent' => 'mi-app/2.0', 'log_payloads' => true],
        ])->getDefinition('finkok.transport')->getArguments();

        self::assertSame(7.5, $arguments[1]);
        self::assertTrue($arguments[3]);
        self::assertSame('mi-app/2.0', $arguments[4]);
    }

    public function testSePuedeDesactivarElReintentoAutomatico(): void
    {
        $factory = $this->load(['http' => ['retry' => ['enabled' => false, 'max_retries' => 0]]])
            ->getDefinition('finkok.http_client_factory');

        self::assertFalse($factory->getArgument('$retryEnabled'));
        self::assertSame(0, $factory->getArgument('$maxRetries'));
    }

    public function testRechazaOpcionesDeConfiguracionDesconocidas(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $this->load(['profiles' => ['matriz' => ['username' => 'u', 'password' => 'p']]]);
    }
}
