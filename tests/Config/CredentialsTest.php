<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Tests\Config;

use Finkok\CfdiBundle\Config\Credentials;
use Finkok\CfdiBundle\Config\Environment;
use Finkok\CfdiBundle\Config\Service;
use Finkok\CfdiBundle\Exception\ConfigurationException;
use Finkok\CfdiBundle\Tests\Concerns\ManagesTempFiles;
use PHPUnit\Framework\TestCase;

final class CredentialsTest extends TestCase
{
    use ManagesTempFiles;

    protected function tearDown(): void
    {
        $this->cleanupTemporaryFiles();
    }

    public function testExponeLosDatosDelPerfil(): void
    {
        $credentials = new Credentials(
            name: 'sucursal_norte',
            username: 'usuario@demo.com',
            password: 'clave',
            taxpayerId: 'eku9003173c9',
            environment: Environment::Production,
        );

        self::assertSame('sucursal_norte', $credentials->name());
        self::assertSame('usuario@demo.com', $credentials->username());
        self::assertSame('clave', $credentials->password());
        self::assertSame('EKU9003173C9', $credentials->taxpayerId());
        self::assertSame(Environment::Production, $credentials->environment());
        self::assertFalse($credentials->hasCsd());
    }

    public function testExigeUsuarioYContrasena(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/usuario de Finkok/');

        new Credentials('perfil', '', 'clave');
    }

    public function testExigeCertificadoYLlaveJuntos(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/certificado y la llave privada juntos/');

        new Credentials('perfil', 'usuario', 'clave', null, Environment::Demo, 'certificado.cer');
    }

    public function testRequiereElRfcDelEmisorCuandoSeNecesita(): void
    {
        $credentials = new Credentials('perfil', 'usuario', 'clave');

        self::assertNull($credentials->taxpayerId());

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/taxpayer_id/');

        $credentials->requireTaxpayerId();
    }

    public function testCodificaElCsdDeFormaPerezosaYSoloSiEstaConfigurado(): void
    {
        $cerPath = $this->temporaryFile('DER-DEL-CERTIFICADO', 'emisor.cer');
        $keyPath = $this->temporaryFile('DER-DE-LA-LLAVE', 'emisor.key');

        $credentials = new Credentials(
            name: 'con-csd',
            username: 'usuario',
            password: 'clave',
            taxpayerId: 'EKU9003173C9',
            environment: Environment::Demo,
            certificate: $cerPath,
            privateKey: $keyPath,
            privateKeyPassphrase: '12345678a',
        );

        self::assertTrue($credentials->hasCsd());
        self::assertSame(base64_encode('DER-DEL-CERTIFICADO'), $credentials->certificateBase64());
        self::assertSame(base64_encode('DER-DE-LA-LLAVE'), $credentials->privateKeyBase64());
    }

    public function testSinCsdNoHayPayloads(): void
    {
        $credentials = new Credentials('sin-csd', 'usuario', 'clave');

        self::assertNull($credentials->certificateBase64());
        self::assertNull($credentials->privateKeyBase64());
    }

    public function testPermiteApuntarElMismoPerfilAOtroAmbiente(): void
    {
        $credentials = new Credentials('perfil', 'usuario', 'clave', 'EKU9003173C9', Environment::Demo);

        $produccion = $credentials->withEnvironment(Environment::Production);

        self::assertSame(Environment::Production, $produccion->environment());
        self::assertSame(Environment::Demo, $credentials->environment(), 'El perfil original no debe mutar.');
        self::assertSame($credentials, $credentials->withEnvironment(Environment::Demo));
    }

    public function testResuelveElEndpointSobrescritoDelPerfil(): void
    {
        $credentials = new Credentials(
            name: 'perfil',
            username: 'usuario',
            password: 'clave',
            taxpayerId: null,
            environment: Environment::Demo,
            certificate: null,
            privateKey: null,
            privateKeyPassphrase: null,
            endpoints: ['stamp' => ['demo' => 'https://otro-host/stamp']],
        );

        self::assertSame('https://otro-host/stamp', $credentials->endpoint(Service::Stamp, Environment::Demo));
        self::assertNull($credentials->endpoint(Service::Stamp, Environment::Production));
        self::assertNull($credentials->endpoint(Service::Cancel, Environment::Demo));
    }

    public function testNuncaExponeLaContrasenaAlVolcarElObjeto(): void
    {
        $credentials = new Credentials('perfil', 'usuario', 'super-secreta', 'EKU9003173C9');

        $dump = $credentials->__debugInfo();

        self::assertSame('***', $dump['password']);
        self::assertSame('usuario', $dump['username']);
        self::assertStringNotContainsString('super-secreta', print_r($dump, true));
    }
}
