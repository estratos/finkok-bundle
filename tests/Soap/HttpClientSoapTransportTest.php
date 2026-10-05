<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Tests\Soap;

use Finkok\CfdiBundle\Exception\SoapFaultException;
use Finkok\CfdiBundle\Exception\TransportException;
use Finkok\CfdiBundle\Exception\UnexpectedResponseException;
use Finkok\CfdiBundle\Soap\HttpClientSoapTransport;
use Finkok\CfdiBundle\Soap\SoapRequest;
use Finkok\CfdiBundle\Soap\Value\Base64Value;
use Finkok\CfdiBundle\Tests\Concerns\ReadsHttpHeaders;
use Finkok\CfdiBundle\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpClientSoapTransportTest extends TestCase
{
    use ReadsHttpHeaders;

    /**
     * @param list<MockResponse> $responses
     */
    private function transport(array $responses, ?array &$captured = null): HttpClientSoapTransport
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses, &$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        });

        return new HttpClientSoapTransport($client, 30.0);
    }

    private function request(): SoapRequest
    {
        return new SoapRequest(
            endpoint: 'https://demo-facturacion.finkok.com/servicios/soap/stamp',
            operation: 'stamp',
            namespace: 'http://facturacion.finkok.com/stamp',
            arguments: [
                'xml' => Base64Value::fromBinary('<cfdi:Comprobante/>'),
                'username' => 'usuario@demo.com',
                'password' => 'clave',
            ],
        );
    }

    public function testEnviaUnPostConLasCabecerasSoap11Esperadas(): void
    {
        $captured = null;
        $transport = $this->transport([new MockResponse(Fixtures::response('stamp-success'))], $captured);

        $response = $transport->send($this->request());

        self::assertNotNull($captured);
        self::assertSame('POST', $captured['method']);
        self::assertSame('https://demo-facturacion.finkok.com/servicios/soap/stamp', $captured['url']);
        self::assertSame('text/xml; charset=utf-8', $this->headerOf($captured['options'], 'Content-Type'));
        self::assertSame('"stamp"', $this->headerOf($captured['options'], 'SOAPAction'));
        self::assertSame(30.0, $captured['options']['timeout']);
        self::assertSame('Comprobante timbrado satisfactoriamente', $response->resultText('CodEstatus', 'stamp'));
    }

    public function testElCuerpoEnviadoContieneElXmlCodificadoUnaSolaVez(): void
    {
        $captured = null;
        $this->transport([new MockResponse(Fixtures::response('stamp-success'))], $captured)->send($this->request());

        self::assertNotNull($captured);
        $body = (string) $captured['options']['body'];

        self::assertStringContainsString('<tns:xml>'.base64_encode('<cfdi:Comprobante/>').'</tns:xml>', $body);
        self::assertStringNotContainsString(base64_encode(base64_encode('<cfdi:Comprobante/>')), $body);
    }

    public function testUnSoapFaultSeConvierteEnSoapFaultException(): void
    {
        $transport = $this->transport([new MockResponse(Fixtures::response('soap-fault'), ['http_code' => 500])]);

        $this->expectException(SoapFaultException::class);

        $transport->send($this->request());
    }

    public function testUnHttp502ConHtmlSeConvierteEnTransportException(): void
    {
        $transport = $this->transport([
            new MockResponse(Fixtures::contents('responses/http-502.html'), ['http_code' => 502]),
        ]);

        try {
            $transport->send($this->request());
            self::fail('Se esperaba una TransportException.');
        } catch (TransportException $exception) {
            self::assertSame(502, $exception->getHttpStatusCode());
            self::assertTrue($exception->isRetryable());
            self::assertSame('stamp', $exception->getOperation());
            self::assertStringContainsString('502', $exception->getMessage());
        }
    }

    public function testUnHttp401SinSoapFaultNoEsReintentable(): void
    {
        $transport = $this->transport([new MockResponse('no autorizado', ['http_code' => 401])]);

        try {
            $transport->send($this->request());
            self::fail('Se esperaba una TransportException.');
        } catch (TransportException $exception) {
            self::assertSame(401, $exception->getHttpStatusCode());
            self::assertFalse($exception->isRetryable());
        }
    }

    public function testUnHttp200ConCuerpoQueNoEsEnvelopeSoapSeReportaComoRespuestaInesperada(): void
    {
        $transport = $this->transport([new MockResponse('<html>portal cautivo</html>', ['http_code' => 200])]);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessageMatches('/envelope SOAP/');

        $transport->send($this->request());
    }
}
