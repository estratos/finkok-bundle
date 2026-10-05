<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Tests\Xml;

use Finkok\CfdiBundle\Xml\DomReader;
use PHPUnit\Framework\TestCase;

final class DomReaderTest extends TestCase
{
    private function document(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }

    public function testDistingueMayusculasPorqueElWsdlUsaUuidUuidYUUID(): void
    {
        $document = $this->document(
            '<resultado>'
            .'<UUID>ACUSE</UUID>'
            .'<Uuid>INCIDENCIA</Uuid>'
            .'<uuid>FOLIO</uuid>'
            .'</resultado>',
        );

        self::assertSame('ACUSE', DomReader::textOf($document->documentElement, 'UUID'));
        self::assertSame('INCIDENCIA', DomReader::textOf($document->documentElement, 'Uuid'));
        self::assertSame('FOLIO', DomReader::textOf($document->documentElement, 'uuid'));
    }

    public function testIgnoraElPrefijoYBuscaPorNombreLocal(): void
    {
        $document = $this->document(
            '<s0:stampResult xmlns:s0="apps.services.soap.core.views">'
            .'<s0:CodEstatus>Comprobante timbrado satisfactoriamente</s0:CodEstatus>'
            .'</s0:stampResult>',
        );

        self::assertSame(
            'Comprobante timbrado satisfactoriamente',
            DomReader::textOf($document->documentElement, 'CodEstatus'),
        );
    }

    public function testNormalizaRetornosDeCarroYSaltosDeLinea(): void
    {
        $document = $this->document("<resultado><xml>linea1\r\nlinea2\rlinea3\n   </xml></resultado>");

        self::assertSame("linea1\nlinea2\nlinea3", DomReader::text($document->getElementsByTagName('xml')->item(0)));
    }

    public function testDevuelveNullParaNodosVacios(): void
    {
        $document = $this->document('<resultado><xml/>  <Incidencias/></resultado>');

        self::assertNull(DomReader::textOf($document->documentElement, 'xml'));
        self::assertNull(DomReader::textOf($document->documentElement, 'NoExiste'));
        self::assertNull(DomReader::text($document->getElementsByTagName('Incidencias')->item(0)));
    }

    public function testDesescapaUnXmlDoblementeCodificado(): void
    {
        $document = $this->document(
            '<resultado><xml>&lt;?xml version="1.0"?&gt;&lt;cfdi:Comprobante/&gt;</xml></resultado>',
        );

        $text = DomReader::xmlText($document->getElementsByTagName('xml')->item(0));

        self::assertIsString($text);
        self::assertStringStartsWith('<?xml', $text);
        self::assertStringContainsString('<cfdi:Comprobante/>', $text);
    }

    public function testNoRompeLasEntidadesLegitimasDeUnXmlBienFormado(): void
    {
        $document = $this->document(
            '<resultado><xml>&lt;?xml version="1.0"?&gt;&lt;cfdi:Comprobante Descripcion="A &amp;amp; B"/&gt;</xml></resultado>',
        );

        $text = DomReader::xmlText($document->getElementsByTagName('xml')->item(0));

        // El XML estaba escapado una sola vez: se conserva tal cual y `&amp;`
        // sigue siendo una entidad válida dentro del CFDI.
        self::assertIsString($text);
        self::assertStringContainsString('&amp;', $text);
    }

    public function testCuentaHijosDirectosYDescendientes(): void
    {
        $document = $this->document(
            '<Incidencias><Incidencia><CodigoError>307</CodigoError></Incidencia>'
            .'<Incidencia><CodigoError>705</CodigoError></Incidencia></Incidencias>',
        );

        self::assertCount(2, DomReader::elements($document->documentElement, 'Incidencia'));
        self::assertCount(2, DomReader::descendants($document->documentElement, 'CodigoError'));
        self::assertSame('307', DomReader::descendant($document->documentElement, 'CodigoError')?->textContent);
        self::assertTrue(DomReader::hasChild($document->documentElement, 'Incidencia'));
    }

    public function testConvierteBooleanosYEnteros(): void
    {
        self::assertTrue(DomReader::toBoolean('true'));
        self::assertFalse(DomReader::toBoolean('false'));
        self::assertTrue(DomReader::toBoolean('1'));
        self::assertNull(DomReader::toBoolean(null));
        self::assertSame(3, DomReader::toInt('3'));
        self::assertNull(DomReader::toInt('abc'));
        self::assertNull(DomReader::toInt(null));
    }

    public function testLeeAtributosSinNamespace(): void
    {
        $document = $this->document('<UUID UUID=" A1B2 " FolioSustitucion="" Otro="x"/>');

        self::assertSame('A1B2', DomReader::attribute($document->documentElement, 'UUID'));
        self::assertNull(DomReader::attribute($document->documentElement, 'FolioSustitucion'));
        self::assertNull(DomReader::attribute($document->documentElement, 'Inexistente'));
    }
}
