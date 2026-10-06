<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Config;

use Estratos\FinkokBundle\Config\Credentials;
use Estratos\FinkokBundle\Config\CredentialsProvider;
use Estratos\FinkokBundle\Config\Environment;
use Estratos\FinkokBundle\Config\Service;
use Estratos\FinkokBundle\Exception\ConfigurationException;
use Estratos\FinkokBundle\Exception\ProfileNotFoundException;
use PHPUnit\Framework\TestCase;

final class CredentialsProviderTest extends TestCase
{
    private function provider(?string $default = 'principal'): CredentialsProvider
    {
        return new CredentialsProvider(
            [
                'principal' => new Credentials('principal', 'matriz@demo.com', 'clave', 'EKU9003173C9', Environment::Demo),
                'sucursal' => new Credentials('sucursal', 'sucursal@demo.com', 'otra', 'MISC491214B86', Environment::Production),
            ],
            $default,
        );
    }

    public function testDevuelveElPerfilPorDefectoCuandoNoSeIndicaNombre(): void
    {
        $provider = $this->provider();

        self::assertSame('principal', $provider->get()->name());
        self::assertSame('principal', $provider->get(null)->name());
        self::assertSame('principal', $provider->get('')->name());
    }

    public function testDevuelveElPerfilSolicitado(): void
    {
        $provider = $this->provider();

        $sucursal = $provider->get('sucursal');

        self::assertSame('sucursal', $sucursal->name());
        self::assertSame('MISC491214B86', $sucursal->taxpayerId());
        self::assertSame(Environment::Production, $sucursal->environment());
    }

    public function testListaLosPerfilesDisponibles(): void
    {
        $provider = $this->provider();

        self::assertSame(['principal', 'sucursal'], $provider->names());
        self::assertTrue($provider->has('principal'));
        self::assertFalse($provider->has('inexistente'));
    }

    public function testUnPerfilDesconocidoFallaConLaListaDeDisponibles(): void
    {
        try {
            $this->provider()->get('inexistente');
            self::fail('Se esperaba ProfileNotFoundException.');
        } catch (ProfileNotFoundException $exception) {
            self::assertStringContainsString('inexistente', $exception->getMessage());
            self::assertStringContainsString('"principal", "sucursal"', $exception->getMessage());
        }
    }

    public function testConUnSoloPerfilNoHaceFaltaIndicarElDefault(): void
    {
        $provider = new CredentialsProvider([
            'unico' => new Credentials('unico', 'usuario', 'clave', 'EKU9003173C9'),
        ]);

        self::assertSame('unico', $provider->default()->name());
    }

    public function testConVariosPerfilesEsObligatorioIndicarElDefault(): void
    {
        $provider = $this->provider(null);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/default_profile/');

        $provider->default();
    }

    public function testNoSePuedeConstruirSinPerfiles(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/al menos uno/');

        new CredentialsProvider([], null);
    }

    public function testUnDefaultQueNoExisteEnLosPerfilesEsUnErrorClaro(): void
    {
        $this->expectException(ProfileNotFoundException::class);

        (new CredentialsProvider(
            ['principal' => new Credentials('principal', 'usuario', 'clave')],
            'fantasma',
        ))->default();
    }

    public function testResuelveLaUrlDeCadaServicioSegunElAmbiente(): void
    {
        self::assertSame(
            'https://demo-facturacion.finkok.com/servicios/soap/stamp',
            Service::Stamp->defaultEndpoint(Environment::Demo),
        );
        self::assertSame(
            'https://facturacion.finkok.com/servicios/soap/stamp',
            Service::Stamp->defaultEndpoint(Environment::Production),
        );
        self::assertSame(
            'https://demo-facturacion.finkok.com/servicios/soap/cancel',
            Service::Cancel->defaultEndpoint(Environment::Demo),
        );
        self::assertSame('http://facturacion.finkok.com/stamp', Service::Stamp->namespace());
        self::assertSame('http://facturacion.finkok.com/cancel', Service::Cancel->namespace());
    }
}
