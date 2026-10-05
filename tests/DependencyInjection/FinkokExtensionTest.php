<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Tests\DependencyInjection;

use Finkok\CfdiBundle\Config\CredentialsProviderInterface;
use Finkok\CfdiBundle\Config\Environment;
use Finkok\CfdiBundle\Contract\CancelServiceInterface;
use Finkok\CfdiBundle\Contract\StampServiceInterface;
use Finkok\CfdiBundle\Csd\CsdEncoderInterface;
use Finkok\CfdiBundle\DependencyInjection\Compiler\WiringPass;
use Finkok\CfdiBundle\DependencyInjection\FinkokExtension;
use Finkok\CfdiBundle\Service\CancelService;
use Finkok\CfdiBundle\Service\StampService;
use Finkok\CfdiBundle\Soap\SoapTransportInterface;
use Finkok\CfdiBundle\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FinkokExtensionTest extends TestCase
{
    /**
     * Carga la extensión y ejecuta el compiler pass sin compilar.
     *
     * Se usa para inspeccionar las definiciones tal como las crea la extensión:
     * al compilar, Symfony inyecta los servicios privados en los públicos y los
     * renombra, de modo que los ids internos dejan de existir.
     *
     * @param array<string, mixed> $config
     */
    private function load(array $config, ?ContainerBuilder $container = null): ContainerBuilder
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
    private function compile(array $config, ?ContainerBuilder $container = null): ContainerBuilder
    {
        $container = $this->load($config, $container);
        $container->compile();

        return $container;
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalConfig(): array
    {
        return [
            'default_profile' => 'matriz',
            'profiles' => [
                'matriz' => [
                    'username' => 'usuario@demo.com',
                    'password' => 'clave',
                    'taxpayer_id' => 'EKU9003173C9',
                    'environment' => 'demo',
                ],
                'sucursal' => [
                    'username' => 'sucursal@demo.com',
                    'password' => 'otra',
                    'environment' => 'production',
                ],
            ],
        ];
    }

    public function testCompilaYExponeLosServiciosAutowireables(): void
    {
        $container = $this->compile($this->minimalConfig());

        self::assertInstanceOf(StampService::class, $container->get(StampServiceInterface::class));
        self::assertInstanceOf(CancelService::class, $container->get(CancelServiceInterface::class));
        self::assertInstanceOf(SoapTransportInterface::class, $container->get(SoapTransportInterface::class));
        self::assertInstanceOf(CsdEncoderInterface::class, $container->get(CsdEncoderInterface::class));
        self::assertInstanceOf(CredentialsProviderInterface::class, $container->get(CredentialsProviderInterface::class));
    }

    public function testRegistraCadaPerfilComoUnServicioIndependiente(): void
    {
        $container = $this->load($this->minimalConfig());

        self::assertTrue($container->hasDefinition('finkok.credentials.matriz'));
        self::assertTrue($container->hasDefinition('finkok.credentials.sucursal'));

        $container->compile();
        $provider = $container->get(CredentialsProviderInterface::class);

        self::assertSame(['matriz', 'sucursal'], $provider->names());
        self::assertSame('matriz', $provider->get()->name());
        self::assertSame(Environment::Production, $provider->get('sucursal')->environment());
        self::assertSame('EKU9003173C9', $provider->get('matriz')->taxpayerId());
    }

    public function testAplicaLosValoresPorDefectoDeLaConfiguracion(): void
    {
        $factory = $this->load($this->minimalConfig())->getDefinition('finkok.http_client_factory');

        self::assertSame(30.0, $factory->getArgument('$options')['timeout']);
        self::assertTrue($factory->getArgument('$retryEnabled'));
        self::assertSame(2, $factory->getArgument('$maxRetries'));
        self::assertNull(
            $factory->getArgument('$base'),
            'Sin el servicio http_client en la aplicación, la fábrica crea un cliente propio.',
        );
    }

    public function testConectaElClienteHttpDeLaAplicacionCuandoExiste(): void
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class)->setPublic(true);

        $factory = $this->load($this->minimalConfig(), $container)->getDefinition('finkok.http_client_factory');

        $base = $factory->getArgument('$base');

        self::assertInstanceOf(Reference::class, $base);
        self::assertSame('http_client', (string) $base);
    }

    public function testUsaElClienteHttpDeLaAplicacionDeExtremoAExtremo(): void
    {
        $recorded = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = $url;

            return new MockResponse(Fixtures::response('stamp-success'));
        });

        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class)->setSynthetic(true)->setPublic(true);
        $container = $this->load($this->minimalConfig(), $container);
        $container->compile();
        $container->set('http_client', $client);

        $receipt = $container->get(StampServiceInterface::class)->stamp(Fixtures::signedCfdi());

        self::assertTrue($receipt->isSuccess());
        self::assertSame('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', $receipt->uuid);
        self::assertSame(['https://demo-facturacion.finkok.com/servicios/soap/stamp'], $recorded);
    }

    public function testRegistraUnLoggerNuloCuandoLaAplicacionNoTieneLogger(): void
    {
        $container = $this->load($this->minimalConfig());

        self::assertTrue($container->hasDefinition(WiringPass::NULL_LOGGER_SERVICE));
        self::assertSame(WiringPass::NULL_LOGGER_SERVICE, (string) $container->getAlias(WiringPass::LOGGER_SERVICE));
    }

    public function testUsaElLoggerDeLaAplicacionCuandoExiste(): void
    {
        $container = new ContainerBuilder();
        $container->register('logger', \Psr\Log\NullLogger::class)->setPublic(true);

        $container = $this->load($this->minimalConfig(), $container);

        self::assertSame('logger', (string) $container->getAlias(WiringPass::LOGGER_SERVICE));
    }

    public function testExigeAlMenosUnPerfil(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['profiles' => []]);
    }

    public function testElPerfilPorDefectoDebeExistir(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/default_profile/');

        $this->load([
            'default_profile' => 'fantasma',
            'profiles' => [
                'matriz' => ['username' => 'usuario', 'password' => 'clave'],
            ],
        ]);
    }

    public function testElCertificadoYLaLlaveDebenDeclararseJuntos(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/certificate.*private_key/s');

        $this->load([
            'profiles' => [
                'matriz' => [
                    'username' => 'usuario',
                    'password' => 'clave',
                    'certificate' => '/ruta/emisor.cer',
                ],
            ],
        ]);
    }

    public function testExigeUsuarioYContrasenaEnCadaPerfil(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'profiles' => [
                'matriz' => ['username' => 'usuario'],
            ],
        ]);
    }

    public function testRechazaUnAmbienteDesconocido(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'profiles' => [
                'matriz' => ['username' => 'usuario', 'password' => 'clave', 'environment' => 'staging'],
            ],
        ]);
    }

    public function testPermiteSobrescribirEndpointsTimeoutYValidacionPrevia(): void
    {
        $config = $this->minimalConfig();
        $config['endpoints'] = ['stamp' => ['demo' => 'https://proxy.test/stamp']];
        $config['http'] = ['timeout' => 12.5];
        $config['preflight'] = ['enabled' => false];

        $container = $this->load($config);

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

    public function testLosPerfilesConNombresRarosGeneranIdsValidos(): void
    {
        $container = $this->load([
            'profiles' => [
                'sucursal norte/x' => ['username' => 'usuario', 'password' => 'clave'],
            ],
        ]);

        self::assertTrue($container->hasDefinition('finkok.credentials.sucursal_norte_x'));

        $container->compile();
        $provider = $container->get(CredentialsProviderInterface::class);

        self::assertSame(['sucursal norte/x'], $provider->names());
        self::assertSame('sucursal norte/x', $provider->get()->name());
    }

    public function testLosNombresQueColisionanNoSePisan(): void
    {
        $container = $this->load([
            'profiles' => [
                'a/b' => ['username' => 'uno', 'password' => 'clave'],
                'a_b' => ['username' => 'dos', 'password' => 'clave'],
            ],
        ]);

        $definitions = array_filter(
            array_keys($container->getDefinitions()),
            static fn (string $id): bool => str_starts_with($id, 'finkok.credentials.'),
        );

        self::assertCount(2, $definitions);
    }

    public function testConfiguraElTimeoutDelTransporteYElUserAgent(): void
    {
        $config = $this->minimalConfig();
        $config['http']['timeout'] = 7.5;
        $config['http']['user_agent'] = 'mi-app/2.0';
        $config['http']['log_payloads'] = true;

        $arguments = $this->load($config)->getDefinition('finkok.transport')->getArguments();

        self::assertSame(7.5, $arguments[1]);
        self::assertTrue($arguments[3]);
        self::assertSame('mi-app/2.0', $arguments[4]);
    }

    public function testSePuedeDesactivarElReintentoAutomatico(): void
    {
        $config = $this->minimalConfig();
        $config['http'] = ['retry' => ['enabled' => false, 'max_retries' => 0]];

        $factory = $this->load($config)->getDefinition('finkok.http_client_factory');

        self::assertFalse($factory->getArgument('$retryEnabled'));
        self::assertSame(0, $factory->getArgument('$maxRetries'));
    }
}
