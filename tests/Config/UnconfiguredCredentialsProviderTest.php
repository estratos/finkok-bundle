<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Config;

use Estratos\FinkokBundle\Config\CredentialsProviderInterface;
use Estratos\FinkokBundle\Config\UnconfiguredCredentialsProvider;
use Estratos\FinkokBundle\Exception\ConfigurationException;
use Estratos\FinkokBundle\Exception\FinkokExceptionInterface;
use PHPUnit\Framework\TestCase;

final class UnconfiguredCredentialsProviderTest extends TestCase
{
    private function provider(): UnconfiguredCredentialsProvider
    {
        return new UnconfiguredCredentialsProvider();
    }

    public function testImplementaElContratoDelBundle(): void
    {
        self::assertInstanceOf(CredentialsProviderInterface::class, $this->provider());
    }

    public function testNoFingeTenerPerfiles(): void
    {
        self::assertSame([], $this->provider()->names());
        self::assertFalse($this->provider()->has('matriz'));
    }

    public function testFallaAlPedirUnPerfilConUnMensajeAccionable(): void
    {
        try {
            $this->provider()->get('matriz');
            self::fail('Se esperaba ConfigurationException.');
        } catch (ConfigurationException $exception) {
            self::assertInstanceOf(FinkokExceptionInterface::class, $exception);
            self::assertStringContainsString('no ha registrado un proveedor de credenciales', $exception->getMessage());
            self::assertStringContainsString('CredentialsProviderInterface', $exception->getMessage());
            self::assertStringContainsString('CredentialsProvider', $exception->getMessage());
            self::assertStringContainsString('README', $exception->getMessage());
        }
    }

    public function testFallaTambienAlPedirElPerfilPorDefecto(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->provider()->default();
    }

    public function testFallaAlPedirElPerfilPorDefectoImplicito(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->provider()->get();
    }
}
