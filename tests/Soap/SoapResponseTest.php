<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Soap;

use Estratos\FinkokBundle\Exception\SoapFaultException;
use Estratos\FinkokBundle\Soap\SoapResponse;
use Estratos\FinkokBundle\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

final class SoapResponseTest extends TestCase
{
    public function testDetectaElSoapFaultYNoConfundeSusCampos(): void
    {
        $response = new SoapResponse(Fixtures::response('soap-fault'), 500);

        self::assertTrue($response->hasFault());

        $fault = $response->fault();

        self::assertNotNull($fault);
        self::assertSame('soap:Client', $fault->code);
        self::assertStringContainsString('no pudo procesar la petición', (string) $fault->message);
        self::assertTrue($fault->isClientFault());
    }

    public function testAssertNoFaultLanzaLaExcepcionConEndpointYOperacion(): void
    {
        $response = new SoapResponse(Fixtures::response('soap-fault'), 500);

        $this->expectException(SoapFaultException::class);
        $this->expectExceptionMessageMatches('/stamp/');

        $response->assertNoFault('https://demo-facturacion.finkok.com/servicios/soap/stamp', 'stamp');
    }

    public function testExtraeElResultadoDeLaOperacionPorSuNombre(): void
    {
        $response = new SoapResponse(Fixtures::response('stamp-success'));

        $result = $response->result('stamp');

        self::assertNotNull($result);
        self::assertSame('stampResult', $result->localName);
        self::assertSame(
            'Comprobante timbrado satisfactoriamente',
            $response->resultText('CodEstatus', 'stamp'),
        );
    }

    public function testExtraeElResultadoSinIndicarLaOperacion(): void
    {
        $response = new SoapResponse(Fixtures::response('cancel-accepted'));

        self::assertSame('cancelResult', $response->result()?->localName);
    }

    public function testUnXmlValidoQueNoEsEnvelopeSoapNoSeConfundeConUnaRespuesta(): void
    {
        // La página de error de un proxy puede ser XML bien formado: no basta con
        // comprobar que el cuerpo parsea, hay que verificar que sea un envelope.
        $response = new SoapResponse(Fixtures::contents('responses/http-502.html'), 502);

        self::assertTrue($response->isXml());
        self::assertFalse($response->isSoapEnvelope());
        self::assertNull($response->envelope());
        self::assertNull($response->body());
        self::assertNull($response->fault());
        self::assertNull($response->result('stamp'));
    }

    public function testReconoceElCuerpoQueNoEsXml(): void
    {
        $response = new SoapResponse('<html><p>sin cerrar', 200);

        self::assertFalse($response->isXml());
        self::assertFalse($response->isSoapEnvelope());
        self::assertNull($response->result('stamp'));
    }

    public function testReconoceElCuerpoVacio(): void
    {
        $response = new SoapResponse('', 204);

        self::assertFalse($response->isXml());
        self::assertFalse($response->isSoapEnvelope());
        self::assertTrue($response->headers() === []);
    }

    public function testElResultadoNuloNoRompeCuandoLaRespuestaNoTraeResultado(): void
    {
        $response = new SoapResponse(
            '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body/></soap:Envelope>',
        );

        self::assertTrue($response->isXml());
        self::assertNull($response->result('stamp'));
        self::assertNull($response->resultText('CodEstatus', 'stamp'));
    }

    public function testDescribeIncluyeElCodigoHttpYElEstadoDelXml(): void
    {
        $response = new SoapResponse(Fixtures::contents('responses/http-502.html'), 502);

        self::assertStringContainsString('HTTP 502', $response->describe());
        self::assertStringContainsString('XML', $response->describe());
        self::assertStringContainsString('sin fault', $response->describe());
    }

    public function testDetectaElFaultFueraDelBody(): void
    {
        $response = new SoapResponse(
            '<?xml version="1.0"?>'
            .'<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soap:Fault><faultcode>soap:Server</faultcode><faultstring>caído</faultstring></soap:Fault>'
            .'</soap:Envelope>',
            500,
        );

        self::assertTrue($response->hasFault());
        self::assertSame('caído', $response->fault()?->message);
    }
}
