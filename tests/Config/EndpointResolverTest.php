<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Tests\Config;

use Finkok\CfdiBundle\Config\Credentials;
use Finkok\CfdiBundle\Config\EndpointResolver;
use Finkok\CfdiBundle\Config\Environment;
use Finkok\CfdiBundle\Config\Service;
use PHPUnit\Framework\TestCase;

final class EndpointResolverTest extends TestCase
{
    public function testUsaLasUrlsOficialesDeFinkokPorDefecto(): void
    {
        $resolver = new EndpointResolver();

        self::assertSame(
            'https://demo-facturacion.finkok.com/servicios/soap/stamp',
            $resolver->resolve(Service::Stamp, Environment::Demo),
        );
        self::assertSame(
            'https://facturacion.finkok.com/servicios/soap/cancel',
            $resolver->resolve(Service::Cancel, Environment::Production),
        );
    }

    public function testLaConfiguracionGlobalSobrescribeLasUrlsPorDefecto(): void
    {
        $resolver = new EndpointResolver([
            'stamp' => ['demo' => 'https://proxy.interno/finkok/stamp'],
        ]);

        self::assertSame('https://proxy.interno/finkok/stamp', $resolver->resolve(Service::Stamp, Environment::Demo));
        self::assertSame(
            'https://facturacion.finkok.com/servicios/soap/stamp',
            $resolver->resolve(Service::Stamp, Environment::Production),
        );
    }

    public function testElPerfilTieneLaUltimaPalabraSobreLaConfiguracionGlobal(): void
    {
        $resolver = new EndpointResolver([
            'stamp' => ['demo' => 'https://proxy.interno/finkok/stamp'],
        ]);

        $credentials = new Credentials(
            name: 'perfil',
            username: 'usuario',
            password: 'clave',
            taxpayerId: null,
            environment: Environment::Demo,
            certificate: null,
            privateKey: null,
            privateKeyPassphrase: null,
            endpoints: ['stamp' => ['demo' => 'https://otro-host/servicios/soap/stamp']],
        );

        self::assertSame(
            'https://otro-host/servicios/soap/stamp',
            $resolver->resolve(Service::Stamp, Environment::Demo, $credentials),
        );
        // El perfil solo sobrescribe el servicio que declara.
        self::assertSame(
            'https://demo-facturacion.finkok.com/servicios/soap/cancel',
            $resolver->resolve(Service::Cancel, Environment::Demo, $credentials),
        );
    }

    public function testIgnoraSobrescriturasVacias(): void
    {
        $resolver = new EndpointResolver(['stamp' => ['demo' => '   ']]);

        self::assertSame(
            'https://demo-facturacion.finkok.com/servicios/soap/stamp',
            $resolver->resolve(Service::Stamp, Environment::Demo),
        );
    }
}
