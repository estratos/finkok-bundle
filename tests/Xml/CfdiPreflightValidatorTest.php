<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Tests\Xml;

use Finkok\CfdiBundle\Exception\ValidationException;
use Finkok\CfdiBundle\Tests\Fixtures;
use Finkok\CfdiBundle\Xml\CfdiDocument;
use Finkok\CfdiBundle\Xml\CfdiPreflightValidator;
use PHPUnit\Framework\TestCase;

final class CfdiPreflightValidatorTest extends TestCase
{
    public function testUnCfdiFirmadoYSinTimbrarNoGeneraAdvertencias(): void
    {
        $validator = new CfdiPreflightValidator();

        self::assertSame([], $validator->validate(CfdiDocument::fromString(Fixtures::signedCfdi())));
    }

    public function testUnCfdiYaTimbradoNoSeRechazaPorqueEsLaViaDeRecuperacionDel307(): void
    {
        $validator = new CfdiPreflightValidator();

        $warnings = $validator->validate(CfdiDocument::fromString(Fixtures::stampedCfdi()));

        self::assertCount(1, $warnings);
        self::assertStringContainsString('307', $warnings[0]);
        self::assertStringContainsString('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', $warnings[0]);
    }

    public function testRechazaUnXmlVacio(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/está vacío/');

        (new CfdiPreflightValidator())->validate(CfdiDocument::fromString('   '));
    }

    public function testRechazaUnXmlMalFormadoAntesDeGastarUnaLlamada(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/incidencia 301/');

        (new CfdiPreflightValidator())->validate(CfdiDocument::fromString('<cfdi:Comprobante>'));
    }

    public function testRechazaUnXmlQueSuperaElLimiteDeFinkok(): void
    {
        $documento = CfdiDocument::fromString(
            '<?xml version="1.0"?><cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Sello="x">'
            .str_repeat('x', CfdiDocument::MAX_SIZE_BYTES)
            .'</cfdi:Comprobante>',
        );

        try {
            (new CfdiPreflightValidator())->validate($documento);
            self::fail('Se esperaba una ValidationException por tamaño.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('1048576', $exception->getMessage());
            self::assertStringContainsString('1 MB', $exception->getMessage());
        }
    }

    public function testRechazaUnDocumentoQueNoEsUnCfdi(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/elemento raíz/');

        (new CfdiPreflightValidator())->validate(
            CfdiDocument::fromString(Fixtures::contents('not-a-cfdi.xml')),
        );
    }

    public function testRechazaUnComprobanteSinSelloParaEvitarElErrorCfdi40102(): void
    {
        $sinSello = '<?xml version="1.0"?><cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Version="4.0" Total="100.00"/>';

        try {
            (new CfdiPreflightValidator())->validate(CfdiDocument::fromString($sinSello));
            self::fail('Se esperaba una ValidationException por falta de sello.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('CFDI40102', $exception->getMessage());
        }
    }

    public function testSePuedeDesactivarLaExigenciaDeSello(): void
    {
        $sinSello = '<?xml version="1.0"?><cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Version="4.0" Total="100.00"/>';

        $validator = new CfdiPreflightValidator(enabled: true, requireSignature: false);

        self::assertSame([], $validator->validate(CfdiDocument::fromString($sinSello)));
    }

    public function testLaValidacionPreviaSePuedeDesactivarPorCompleto(): void
    {
        $validator = new CfdiPreflightValidator(enabled: false);

        self::assertFalse($validator->isEnabled());
        self::assertSame([], $validator->validate(CfdiDocument::fromString('nada de xml')));
    }
}
