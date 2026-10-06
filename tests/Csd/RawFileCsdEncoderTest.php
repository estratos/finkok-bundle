<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Csd;

use Estratos\FinkokBundle\Csd\RawFileCsdEncoder;
use Estratos\FinkokBundle\Exception\ValidationException;
use Estratos\FinkokBundle\Tests\Concerns\ManagesTempFiles;
use PHPUnit\Framework\TestCase;

final class RawFileCsdEncoderTest extends TestCase
{
    use ManagesTempFiles;

    private const PEM = <<<'PEM'
        -----BEGIN CERTIFICATE-----
        TUlJQ0FEQ0NBQUtDQVFBd0RRWUpLb1pJaHZjTkFRRUxCUUF3RFFZSktvWklodmNO
        -----END CERTIFICATE-----
        PEM;

    protected function tearDown(): void
    {
        $this->cleanupTemporaryFiles();
    }

    public function testCodificaUnArchivoDerUnaSolaVezEnBase64(): void
    {
        $encoder = new RawFileCsdEncoder();
        $path = $this->temporaryFile("\x30\x82\x01\x00DER-BINARIO", 'emisor.cer');

        $encoded = $encoder->encodeCertificate($path);

        self::assertSame(base64_encode("\x30\x82\x01\x00DER-BINARIO"), $encoded);
        self::assertSame("\x30\x82\x01\x00DER-BINARIO", base64_decode($encoded, true));
    }

    public function testConvierteUnCertificadoPemADerAntesDeCodificar(): void
    {
        $encoder = new RawFileCsdEncoder();

        $encoded = $encoder->encodeCertificate(self::PEM);

        self::assertStringNotContainsString('BEGIN CERTIFICATE', base64_decode($encoded, true) ?: '');
        self::assertStringStartsWith('MII', base64_decode($encoded, true) ?: '');

        // El contenido del fixture no es un DER real, así que se comprueba que la
        // armadura PEM se eliminó en lugar del valor exacto.
        self::assertStringNotContainsString('-----', (string) base64_decode($encoded, true));
    }

    public function testLaLlaveSeEnviaSinDescifrarYUnaSolaVez(): void
    {
        $encoder = new RawFileCsdEncoder();
        $path = $this->temporaryFile('DER-DE-LA-LLAVE-CIFRADA', 'emisor.key');

        $encoded = $encoder->encodePrivateKey($path, 'contraseña-de-la-llave');

        self::assertSame(base64_encode('DER-DE-LA-LLAVE-CIFRADA'), $encoded);
    }

    public function testFallaConContenidoVacio(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/está vacío/');

        (new RawFileCsdEncoder())->encodeCertificate('   ');
    }

    public function testUnContenidoQueNoEsArchivoSeTrataComoBytesEnMemoria(): void
    {
        $encoder = new RawFileCsdEncoder();

        $encoded = $encoder->encodeCertificate("\x00\x01BINARIO-EN-MEMORIA");

        self::assertSame("\x00\x01BINARIO-EN-MEMORIA", base64_decode($encoded, true));
    }
}
