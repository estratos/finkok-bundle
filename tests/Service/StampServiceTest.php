<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Service;

use Estratos\FinkokBundle\Config\Environment;
use Estratos\FinkokBundle\Exception\ApiException;
use Estratos\FinkokBundle\Exception\ValidationException;
use Estratos\FinkokBundle\Model\ErrorCode;
use Estratos\FinkokBundle\Tests\Concerns\InteractsWithFinkok;
use Estratos\FinkokBundle\Tests\Concerns\ReadsHttpHeaders;
use Estratos\FinkokBundle\Tests\Fixtures;
use Estratos\FinkokBundle\Xml\CfdiDocument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class StampServiceTest extends TestCase
{
    use InteractsWithFinkok;
    use ReadsHttpHeaders;

    public function testTimbraUnCfdiYDevuelveElAcuseHidratado(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('stamp-success'))]);

        $receipt = $service->stamp(Fixtures::signedCfdi());

        self::assertTrue($receipt->isSuccess());
        self::assertTrue($receipt->isFreshStamp());
        self::assertSame('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', $receipt->uuid);
        self::assertSame('Comprobante timbrado satisfactoriamente', $receipt->status);
        self::assertSame('20001000000300022323', $receipt->satCertificateNumber);
        self::assertStringContainsString('FechaTimbrado', (string) $receipt->xml);
        self::assertSame($receipt->uuid, $receipt->uuidFromXml());
    }

    public function testEnviaAlEndpointDeTimbradoDeDemoConElXmlEnBase64(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('stamp-success'))]);

        $service->stamp(Fixtures::signedCfdi());

        self::assertSame('https://demo-facturacion.finkok.com/servicios/soap/stamp', $this->lastRequestUrl());
        self::assertSame('"stamp"', $this->headerOf($this->firstRequestOptions(), 'SOAPAction'));

        $body = $this->lastRequestBody();
        $xml = Fixtures::signedCfdi();

        self::assertStringContainsString('<tns:username>usuario@demo.com</tns:username>', $body);
        self::assertStringContainsString('<tns:xml>'.base64_encode($xml).'</tns:xml>', $body);
        self::assertStringNotContainsString('<?xml version="1.0" encoding="UTF-8"?><cfdi:Comprobante', $body, 'El CFDI no debe viajar en claro.');
    }

    public function testRecuperaElXmlCuandoFinkokRespondeLaIncidencia307(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('stamp-previously-stamped'))]);

        $receipt = $service->stamp(Fixtures::stampedCfdi());

        self::assertTrue($receipt->isSuccess());
        self::assertTrue($receipt->isPreviouslyStamped());
        self::assertFalse($receipt->isFreshStamp());
        self::assertTrue($receipt->hasStampedXml());
        self::assertTrue($receipt->hasErrorCode(ErrorCode::AlreadyStamped));
        self::assertSame('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', $receipt->uuidFromXml());
    }

    public function testUnaIncidencia705NoEsExitosaYNoLanzaExcepcionPorSiSola(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('stamp-invalid-xml'))]);

        $receipt = $service->stamp(Fixtures::signedCfdi());

        self::assertFalse($receipt->isSuccess());
        self::assertFalse($receipt->hasStampedXml());
        self::assertSame(['705'], $receipt->getErrorCodes());
        self::assertStringContainsString('XML Estructura inválida', (string) $receipt->getErrorMessage());

        $this->expectException(ApiException::class);
        $receipt->assertSuccess();
    }

    public function testUnSoapFaultSePropagaComoExcepcionDeProtocolo(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('soap-fault'), ['http_code' => 500])]);

        $this->expectException(\Estratos\FinkokBundle\Exception\SoapFaultException::class);

        $service->stamp(Fixtures::signedCfdi());
    }

    public function testQuickStampUsaSuPropiaOperacion(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('stamp-success'))]);

        $service->quickStamp(Fixtures::signedCfdi());

        self::assertSame('"quick_stamp"', $this->headerOf($this->firstRequestOptions(), 'SOAPAction'));
        self::assertStringContainsString('<tns:quick_stamp', $this->lastRequestBody());
    }

    public function testStampedYSignStampUsanSusPropiasOperaciones(): void
    {
        $service = $this->stampService([
            new MockResponse(Fixtures::response('stamp-success')),
            new MockResponse(Fixtures::response('stamp-success')),
        ]);

        $service->stamped(Fixtures::stampedCfdi());
        self::assertSame('"stamped"', $this->headerOf($this->firstRequestOptions(), 'SOAPAction'));

        $service->signStamp(Fixtures::signedCfdi());
        self::assertSame('"sign_stamp"', $this->headerOf($this->recordedRequests[1]['options'], 'SOAPAction'));
    }

    public function testConsultaElEstadoDeUnComprobanteEnLaCola(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('query-pending'))]);

        $result = $service->queryPending('7D162D12-F6B6-4BDE-BC8A-BABC4331919A');

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->isStampedNotSent());
        self::assertTrue($result->isPending());
        self::assertFalse($result->isFinished());
        self::assertSame('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', $result->uuid);
        self::assertSame('1', $result->attempts);
        self::assertTrue($result->hasXml());

        $body = $this->lastRequestBody();

        self::assertStringContainsString('<tns:query_pending', $body);
        self::assertStringContainsString('<tns:uuid>7D162D12-F6B6-4BDE-BC8A-BABC4331919A</tns:uuid>', $body);
    }

    public function testUnPerfilExplicitoCambiaCredencialesYAmbiente(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('stamp-success'))]);

        $service->stamp(Fixtures::signedCfdi(), $this->credentialsProvider()->get('sucursal'));

        self::assertSame('https://facturacion.finkok.com/servicios/soap/stamp', $this->lastRequestUrl());
        self::assertStringContainsString('<tns:username>sucursal@demo.com</tns:username>', $this->lastRequestBody());
    }

    public function testLaValidacionPreviaEvitaConsumirUnTimbradoConXmlMalFormado(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('stamp-success'))]);

        try {
            $service->stamp('<cfdi:Comprobante><sin cerrar>');
            self::fail('Se esperaba ValidationException.');
        } catch (ValidationException) {
            self::assertSame([], $this->recordedRequests, 'No debe hacerse ninguna petición HTTP.');
        }
    }

    public function testLaValidacionPreviaAceptaElXmlDeUnCfdiYaTimbrado(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('stamp-previously-stamped'))]);

        $receipt = $service->stamp(CfdiDocument::fromString(Fixtures::stampedCfdi()));

        self::assertTrue($receipt->isSuccess());
        self::assertCount(1, $this->recordedRequests, 'Un CFDI ya timbrado sí debe enviarse: es la vía del 307.');
    }

    public function testSePuedePrevisualizarElEnvelopeSinEnviarlo(): void
    {
        $service = $this->stampService([]);

        $envelope = $service->previewStampRequest(Fixtures::signedCfdi());

        self::assertSame([], $this->recordedRequests, 'La previsualización no debe abrir conexión.');
        self::assertStringContainsString('<tns:stamp xmlns:tns="http://facturacion.finkok.com/stamp">', $envelope);
        self::assertStringContainsString('<tns:password>clave-secreta</tns:password>', $envelope);
    }

    public function testElPerfilPorDefectoSeResuelveSinIndicarlo(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('stamp-success'))]);

        $service->stamp(Fixtures::stampedCfdi());

        self::assertSame(Environment::Demo, $this->credentialsProvider()->get()->environment());
        self::assertStringContainsString('demo-facturacion', $this->lastRequestUrl());
    }
}
