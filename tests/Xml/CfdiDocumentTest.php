<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Xml;

use Estratos\FinkokBundle\Exception\ValidationException;
use Estratos\FinkokBundle\Tests\Concerns\ManagesTempFiles;
use Estratos\FinkokBundle\Tests\Fixtures;
use Estratos\FinkokBundle\Xml\CfdiDocument;
use PHPUnit\Framework\TestCase;

final class CfdiDocumentTest extends TestCase
{
    use ManagesTempFiles;

    protected function tearDown(): void
    {
        $this->cleanupTemporaryFiles();
    }

    public function testLeeLosDatosDeUnCfdiFirmadoSinTimbrar(): void
    {
        $cfdi = CfdiDocument::fromString(Fixtures::signedCfdi());

        self::assertTrue($cfdi->isWellFormed());
        self::assertTrue($cfdi->isCfdi());
        self::assertSame('Comprobante', $cfdi->rootName());
        self::assertSame('4.0', $cfdi->version());
        self::assertSame('EKU9003173C9', $cfdi->emitterRfc());
        self::assertSame('MISC491214B86', $cfdi->receiverRfc());
        self::assertSame('1160.00', $cfdi->total());
        self::assertSame('30001000000400002434', $cfdi->certificateNumber());
        self::assertSame('2024-05-21T10:30:00', $cfdi->issuedAt());
        self::assertTrue($cfdi->hasSignature());
        self::assertFalse($cfdi->hasStamp());
        self::assertNull($cfdi->uuid());
        self::assertStringContainsString('listo para timbrar', $cfdi->describe());
    }

    public function testLeeElUuidDeUnCfdiYaTimbrado(): void
    {
        $cfdi = CfdiDocument::fromString(Fixtures::stampedCfdi());

        self::assertTrue($cfdi->hasStamp());
        self::assertSame('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', $cfdi->uuid());
        self::assertSame('1160.00', $cfdi->total());
        self::assertStringContainsString('ya timbrado', $cfdi->describe());
    }

    public function testDetectaUnXmlMalFormadoSinLanzarWarnings(): void
    {
        $cfdi = CfdiDocument::fromString('<cfdi:Comprobante><sin cerrar>');

        self::assertFalse($cfdi->isWellFormed());
        self::assertFalse($cfdi->isCfdi());
        self::assertNull($cfdi->version());
        self::assertStringContainsString('XML mal formado', $cfdi->describe());

        $this->expectException(ValidationException::class);
        $cfdi->document();
    }

    public function testReconoceQueElDocumentoNoEsUnCfdi(): void
    {
        $cfdi = CfdiDocument::fromString(Fixtures::contents('not-a-cfdi.xml'));

        self::assertTrue($cfdi->isWellFormed());
        self::assertFalse($cfdi->isCfdi());
        self::assertSame('schema', $cfdi->rootName());
    }

    public function testCargaElCfdiDesdeUnArchivoYGuardaSuOrigen(): void
    {
        $path = $this->temporaryFile(Fixtures::signedCfdi(), 'factura.xml');

        $cfdi = CfdiDocument::fromFile($path);

        self::assertSame($path, $cfdi->source());
        self::assertSame('EKU9003173C9', $cfdi->emitterRfc());
    }

    public function testFallaAlLeerUnArchivoInexistente(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/No se puede leer el archivo/');

        CfdiDocument::fromFile($this->temporaryDirectory().'/no-existe.xml');
    }

    public function testCoerceAceptaContenidoRutaOInstancia(): void
    {
        $fromString = CfdiDocument::coerce(Fixtures::signedCfdi());
        self::assertSame('EKU9003173C9', $fromString->emitterRfc());

        $path = $this->temporaryFile(Fixtures::signedCfdi(), 'factura.xml');
        $fromPath = CfdiDocument::coerce($path);
        self::assertSame($path, $fromPath->source());

        $instance = CfdiDocument::fromString(Fixtures::stampedCfdi());
        self::assertSame($instance, CfdiDocument::coerce($instance));
    }

    public function testReportaCuandoElXmlSuperaElLimiteDeUnMegabyte(): void
    {
        $cfdi = CfdiDocument::fromString(Fixtures::signedCfdi());
        self::assertFalse($cfdi->exceedsSizeLimit());

        $grande = CfdiDocument::fromString(
            '<?xml version="1.0"?><cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4">'
            .str_repeat('x', CfdiDocument::MAX_SIZE_BYTES)
            .'</cfdi:Comprobante>',
        );

        self::assertTrue($grande->exceedsSizeLimit());
    }

    public function testQuitaLaDeclaracionXmlCuandoSeSolicita(): void
    {
        $cfdi = CfdiDocument::fromString(Fixtures::signedCfdi());

        self::assertStringStartsWith('<?xml', $cfdi->content());
        self::assertStringStartsWith('<cfdi:Comprobante', $cfdi->contentWithoutDeclaration());
    }
}
