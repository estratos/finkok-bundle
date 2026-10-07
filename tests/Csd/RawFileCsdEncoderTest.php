<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Csd;

use Estratos\FinkokBundle\Csd\RawFileCsdEncoder;
use Estratos\FinkokBundle\Exception\ValidationException;
use Estratos\FinkokBundle\Tests\Concerns\ManagesTempFiles;
use PHPUnit\Framework\TestCase;

/**
 * Codificador alternativo: base64 del archivo tal cual, sin el cifrado DES3 que
 * documenta Finkok. Se conserva como salida de emergencia.
 */
final class RawFileCsdEncoderTest extends TestCase
{
    use ManagesTempFiles;

    protected function tearDown(): void
    {
        $this->cleanupTemporaryFiles();
    }

    public function testCodificaElContenidoUnaSolaVezEnBase64(): void
    {
        $encoder = new RawFileCsdEncoder();
        $path = $this->temporaryFile("\x30\x82\x01\x00DER-BINARIO", 'emisor.cer');

        $encoded = $encoder->encodeCertificate($path);

        self::assertSame(base64_encode("\x30\x82\x01\x00DER-BINARIO"), $encoded);
        self::assertSame("\x30\x82\x01\x00DER-BINARIO", base64_decode($encoded, true));
    }

    public function testNoDobleCodificaElContenido(): void
    {
        $encoder = new RawFileCsdEncoder();

        $encoded = $encoder->encodeCertificate('DER-DE-PRUEBA');

        self::assertSame(base64_encode('DER-DE-PRUEBA'), $encoded);
        self::assertStringNotContainsString(base64_encode(base64_encode('DER-DE-PRUEBA')), $encoded);
    }

    public function testLaLlaveSeEnviaSinCifrarYSinUsarLasContrasenas(): void
    {
        $encoder = new RawFileCsdEncoder();
        $path = $this->temporaryFile('DER-DE-LA-LLAVE', 'emisor.key');

        $encoded = $encoder->encodePrivateKey($path, 'contrasena-de-la-llave', 'contrasena-del-panel');

        self::assertSame(base64_encode('DER-DE-LA-LLAVE'), $encoded);
    }

    public function testFallaConContenidoVacio(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/está vacío/');

        (new RawFileCsdEncoder())->encodePrivateKey('   ', null, 'panel');
    }

    public function testUnContenidoQueNoEsArchivoSeTrataComoBytesEnMemoria(): void
    {
        $encoded = (new RawFileCsdEncoder())->encodeCertificate("\x00\x01BINARIO-EN-MEMORIA");

        self::assertSame("\x00\x01BINARIO-EN-MEMORIA", base64_decode($encoded, true));
    }
}