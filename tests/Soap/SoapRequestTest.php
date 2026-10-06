<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Soap;

use Estratos\FinkokBundle\Soap\SoapNamespaces;
use Estratos\FinkokBundle\Soap\SoapRequest;
use Estratos\FinkokBundle\Soap\Value\AttributeElement;
use Estratos\FinkokBundle\Soap\Value\Base64EncodedValue;
use Estratos\FinkokBundle\Soap\Value\Base64Value;
use Estratos\FinkokBundle\Soap\Value\ComplexValue;
use Estratos\FinkokBundle\Soap\Value\RepeatedValue;
use PHPUnit\Framework\TestCase;

final class SoapRequestTest extends TestCase
{
    /**
     * Elimina la declaración XML y el espacio entre etiquetas para comparar el
     * envelope de forma estable sin depender del formateo del serializador.
     */
    private static function normalize(string $xml): string
    {
        $xml = (string) preg_replace('/^\s*<\?xml[^>]*\?>/', '', $xml);

        return trim((string) preg_replace('/>\s+</', '><', $xml));
    }

    public function testConstruyeElEnvelopeDeTimbradoTalComoLoDocumentaFinkok(): void
    {
        // La forma del envelope (prefijos, orden de argumentos, XML en base64)
        // reproduce el ejemplo publicado por Finkok; los valores son sintéticos.
        $request = new SoapRequest(
            endpoint: 'https://demo-facturacion.finkok.com/servicios/soap/stamp',
            operation: 'stamp',
            namespace: 'http://facturacion.finkok.com/stamp',
            arguments: [
                'xml' => Base64Value::fromBinary('<cfdi:Comprobante/>'),
                'username' => 'usuario@example.com',
                'password' => 'contrasena-de-ejemplo',
            ],
        );

        self::assertSame(
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soap:Body>'
            .'<tns:stamp xmlns:tns="http://facturacion.finkok.com/stamp">'
            .'<tns:xml>PGNmZGk6Q29tcHJvYmFudGUvPg==</tns:xml>'
            .'<tns:username>usuario@example.com</tns:username>'
            .'<tns:password>contrasena-de-ejemplo</tns:password>'
            .'</tns:stamp>'
            .'</soap:Body>'
            .'</soap:Envelope>',
            self::normalize($request->toXml()),
        );
    }

    public function testElSoapActionEsElNombreDeLaOperacion(): void
    {
        $request = new SoapRequest('https://host/stamp', 'quick_stamp', 'urn:x');

        self::assertSame('quick_stamp', $request->soapAction());
    }

    public function testOmiteArgumentosNulosOVaciosParaNoProvocarLaIncidencia705(): void
    {
        $request = new SoapRequest(
            endpoint: 'https://host/cancel',
            operation: 'cancel',
            namespace: 'http://facturacion.finkok.com/cancel',
            arguments: [
                'username' => 'usuario',
                'password' => 'clave',
                'cer' => null,
                'key' => '',
                'taxpayer_id' => 'eku9003173c9',
            ],
        );

        $envelope = $request->toXml();

        self::assertStringNotContainsString('<tns:cer', $envelope);
        self::assertStringNotContainsString('<tns:key', $envelope);
        self::assertStringContainsString('<tns:taxpayer_id>eku9003173c9</tns:taxpayer_id>', $envelope);
    }

    public function testConservaLosBooleanosFalseYLosCeros(): void
    {
        $request = new SoapRequest('https://host/cancel', 'cancel', 'urn:cancel', [
            'store_pending' => false,
            'attempts' => 0,
        ]);

        $envelope = $request->toXml();

        self::assertStringContainsString('<tns:store_pending>false</tns:store_pending>', $envelope);
        self::assertStringContainsString('<tns:attempts>0</tns:attempts>', $envelope);
    }

    public function testConstruyeElArregloUuidsEnElNamespaceDeVistasDelWSDL(): void
    {
        $request = new SoapRequest(
            endpoint: 'https://host/cancel',
            operation: 'cancel',
            namespace: 'http://facturacion.finkok.com/cancel',
            arguments: [
                'UUIDS' => new RepeatedValue('UUID', [
                    new AttributeElement([
                        'UUID' => 'A1B2C3D4-1111-2222-3333-444455556666',
                        'Motivo' => '01',
                        'FolioSustitucion' => 'B2C3D4E5-1111-2222-3333-444455556666',
                    ]),
                    new AttributeElement(['UUID' => 'C3D4E5F6-1111-2222-3333-444455556666']),
                ], SoapNamespaces::VIEWS),
            ],
        );

        // Nota: DOMDocument declara el namespace en cada elemento que crea
        // desprendido del árbol, así que `xmlns:s0` se repite. Es XML válido y
        // cualquier parser conforme lo interpreta igual; se documenta aquí para
        // que un cambio de serialización se detecte de inmediato.
        self::assertSame(
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soap:Body>'
            .'<tns:cancel xmlns:tns="http://facturacion.finkok.com/cancel">'
            .'<tns:UUIDS>'
            .'<s0:UUID xmlns:s0="apps.services.soap.core.views" UUID="A1B2C3D4-1111-2222-3333-444455556666" Motivo="01" FolioSustitucion="B2C3D4E5-1111-2222-3333-444455556666"/>'
            .'<s0:UUID xmlns:s0="apps.services.soap.core.views" UUID="C3D4E5F6-1111-2222-3333-444455556666"/>'
            .'</tns:UUIDS>'
            .'</tns:cancel>'
            .'</soap:Body>'
            .'</soap:Envelope>',
            self::normalize($request->toXml()),
        );
    }

    public function testConstruyeElArregloDeAceptacionRechazoEnElNamespaceDeVistas(): void
    {
        $request = new SoapRequest(
            endpoint: 'https://host/cancel',
            operation: 'accept_reject',
            namespace: 'http://facturacion.finkok.com/cancel',
            arguments: [
                'UUIDS_AR' => new ComplexValue(
                    ['uuids_ar' => new RepeatedValue('UUID_AR', [
                        new ComplexValue(['uuid' => 'A1B2C3D4-1111-2222-3333-444455556666', 'respuesta' => 'Aceptacion']),
                    ])],
                    SoapNamespaces::VIEWS,
                ),
            ],
        );

        self::assertSame(
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soap:Body>'
            .'<tns:accept_reject xmlns:tns="http://facturacion.finkok.com/cancel">'
            .'<tns:UUIDS_AR>'
            .'<s0:uuids_ar xmlns:s0="apps.services.soap.core.views">'
            .'<s0:UUID_AR>'
            .'<s0:uuid>A1B2C3D4-1111-2222-3333-444455556666</s0:uuid>'
            .'<s0:respuesta>Aceptacion</s0:respuesta>'
            .'</s0:UUID_AR>'
            .'</s0:uuids_ar>'
            .'</tns:UUIDS_AR>'
            .'</tns:accept_reject>'
            .'</soap:Body>'
            .'</soap:Envelope>',
            self::normalize($request->toXml()),
        );
    }

    public function testEscapaElContenidoDeTextoDelXml(): void
    {
        $request = new SoapRequest('https://host/stamp', 'stamp', 'urn:stamp', [
            'username' => 'A & B <test>',
        ]);

        $envelope = $request->toXml();

        self::assertStringContainsString('<tns:username>A &amp; B &lt;test&gt;</tns:username>', $envelope);
        self::assertInstanceOf(\DOMDocument::class, $this->parse($envelope));
    }

    public function testBase64EncodedValueNoVuelveACodificarElContenido(): void
    {
        $encoded = base64_encode('contenido-binario');

        $request = new SoapRequest('https://host/cancel', 'cancel', 'urn:cancel', [
            'cer' => Base64EncodedValue::fromEncoded($encoded),
        ]);

        self::assertStringContainsString('<tns:cer>'.$encoded.'</tns:cer>', $request->toXml());
    }

    public function testEnmascaraLaContrasenaEnLosArgumentosDeDepuracion(): void
    {
        $request = new SoapRequest('https://host/stamp', 'stamp', 'urn:stamp', [
            'username' => 'usuario@demo.com',
            'password' => 'super-secreta',
            'xml' => Base64Value::fromBinary(str_repeat('x', 200)),
        ]);

        $debug = $request->debugArguments();

        self::assertSame('***', $debug['password']);
        self::assertSame('usuario@demo.com', $debug['username']);
        self::assertStringContainsString('bytes', $debug['xml']);
    }

    private function parse(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();

        self::assertTrue($document->loadXML($xml), 'El envelope generado debe ser XML válido.');

        return $document;
    }
}
