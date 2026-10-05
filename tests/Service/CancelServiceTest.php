<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Tests\Service;

use Finkok\CfdiBundle\Config\Credentials;
use Finkok\CfdiBundle\Config\CredentialsProvider;
use Finkok\CfdiBundle\Config\EndpointResolver;
use Finkok\CfdiBundle\Config\Environment;
use Finkok\CfdiBundle\Exception\ValidationException;
use Finkok\CfdiBundle\Model\AcceptRejectAnswer;
use Finkok\CfdiBundle\Model\CancellationReason;
use Finkok\CfdiBundle\Model\CancellationUuid;
use Finkok\CfdiBundle\Model\ReceiptType;
use Finkok\CfdiBundle\Service\CancelService;
use Finkok\CfdiBundle\Tests\Concerns\InteractsWithFinkok;
use Finkok\CfdiBundle\Tests\Concerns\ReadsHttpHeaders;
use Finkok\CfdiBundle\Tests\Concerns\ManagesTempFiles;
use Finkok\CfdiBundle\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CancelServiceTest extends TestCase
{
    use InteractsWithFinkok;
    use ReadsHttpHeaders;
    use ManagesTempFiles;

    private const UUID_A = 'A1B2C3D4-1111-2222-3333-444455556666';
    private const UUID_B = 'B2C3D4E5-1111-2222-3333-444455556666';

    protected function tearDown(): void
    {
        $this->cleanupTemporaryFiles();
    }

    private function serviceWithCsd(array $responses): CancelService
    {
        $cer = $this->temporaryFile('DER-CER', 'emisor.cer');
        $key = $this->temporaryFile('DER-KEY', 'emisor.key');

        return new CancelService(
            $this->transport($this->mockHttpClient($responses)),
            new EndpointResolver(),
            new CredentialsProvider([
                'default' => new Credentials(
                    name: 'default',
                    username: 'usuario@demo.com',
                    password: 'clave-secreta',
                    taxpayerId: 'eku9003173c9',
                    environment: Environment::Demo,
                    certificate: $cer,
                    privateKey: $key,
                ),
            ], 'default'),
        );
    }

    public function testCancelaUnUuidConMotivoYFolioDeSustitucion(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('cancel-accepted'))]);

        $receipt = $service->cancel([
            new CancellationUuid(self::UUID_A, CancellationReason::ErrorsWithRelation, self::UUID_B),
        ]);

        self::assertTrue($receipt->isSuccess());
        self::assertSame('201', $receipt->status);
        self::assertSame('EKU9003173C9', $receipt->emitterRfc);

        $body = $this->lastRequestBody();

        self::assertStringContainsString('<tns:UUIDS>', $body);
        self::assertStringContainsString(
            sprintf('<s0:UUID xmlns:s0="apps.services.soap.core.views" UUID="%s" Motivo="01" FolioSustitucion="%s"/>', self::UUID_A, self::UUID_B),
            $body,
        );
        self::assertStringContainsString('<tns:taxpayer_id>EKU9003173C9</tns:taxpayer_id>', $body);
        self::assertStringContainsString('<tns:store_pending>true</tns:store_pending>', $body);
    }

    public function testUsaElEndpointDeCancelacionYLaOperacionCorrecta(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('cancel-accepted'))]);

        $service->cancel([self::UUID_A]);

        self::assertSame('https://demo-facturacion.finkok.com/servicios/soap/cancel', $this->lastRequestUrl());
        self::assertSame('"cancel"', $this->headerOf($this->firstRequestOptions(), 'SOAPAction'));
        self::assertStringContainsString('<tns:cancel xmlns:tns="http://facturacion.finkok.com/cancel">', $this->lastRequestBody());
    }

    public function testRespetaElParametroStorePendingEnFalseParaEvitarElBuffer(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('cancel-accepted'))]);

        $service->cancel([self::UUID_A], storePending: false);

        self::assertStringContainsString('<tns:store_pending>false</tns:store_pending>', $this->lastRequestBody());
    }

    public function testRechazaCancelarSinUuids(): void
    {
        $service = $this->cancelService([]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/"uuids" es obligatorio/');

        $service->cancel([]);
    }

    public function testRechazaElMotivo01SinFolioDeSustitucion(): void
    {
        $service = $this->cancelService([]);

        $this->expectException(ValidationException::class);

        $service->cancel([new CancellationUuid(self::UUID_A, CancellationReason::ErrorsWithRelation)]);
    }

    public function testEnviaElCsdSoloCuandoElPerfilLoTieneYUnaSolaVezCodificado(): void
    {
        $service = $this->serviceWithCsd([new MockResponse(Fixtures::response('cancel-accepted'))]);

        $service->cancel([self::UUID_A]);

        $body = $this->lastRequestBody();

        self::assertStringContainsString('<tns:cer>'.base64_encode('DER-CER').'</tns:cer>', $body);
        self::assertStringContainsString('<tns:key>'.base64_encode('DER-KEY').'</tns:key>', $body);
        self::assertStringNotContainsString(base64_encode(base64_encode('DER-CER')), $body);
    }

    public function testOmiteElCsdCuandoElPerfilNoLoTiene(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('cancel-accepted'))]);

        $service->cancel([self::UUID_A]);

        $body = $this->lastRequestBody();

        self::assertStringNotContainsString('<tns:cer>', $body);
        self::assertStringNotContainsString('<tns:key>', $body);
    }

    public function testUnCodigo205DeUuidNoEncontradoNoEsExito(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('cancel-uuid-not-found'))]);

        $receipt = $service->cancel([self::UUID_A]);

        self::assertFalse($receipt->isSuccess());
        self::assertSame('205', $receipt->getStatusCode());
        self::assertFalse($receipt->cancellationStatusCode()?->isRequestAccepted());
    }

    public function testUnaCancelacionEnProcesoSeReportaComoPendiente(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('cancel-in-process'))]);

        $receipt = $service->cancel([self::UUID_A]);

        self::assertTrue($receipt->isSuccess());
        self::assertTrue($receipt->isInProcess());
        self::assertSame(['7D162D12-F6B6-4BDE-BC8A-BABC4331919A'], $receipt->pendingUuids());
    }

    public function testUnaCancelacionDefinitivaReportaElUuidCancelado(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('cancel-accepted'))]);

        $receipt = $service->cancel([self::UUID_A]);

        self::assertSame(['7D162D12-F6B6-4BDE-BC8A-BABC4331919A'], $receipt->cancelledUuids());
        self::assertTrue($receipt->hasAcknowledgment());
        self::assertStringContainsString('Acuse', (string) $receipt->acknowledgment);
    }

    public function testAceptaYRechazaCancelacionesComoReceptor(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('accept-reject'))]);

        $result = $service->acceptReject([
            self::UUID_A => AcceptRejectAnswer::Accepted,
            self::UUID_B => 'Rechazo',
        ]);

        self::assertTrue($result->isSuccess());
        self::assertSame(1, $result->countAccepted());
        self::assertSame(1, $result->countRejected());
        self::assertTrue($result->accepted[0]->isAccepted());
        self::assertTrue($result->accepted[0]->isAcknowledged());
        self::assertSame('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', $result->accepted[0]->uuid);
        self::assertTrue($result->rejected[0]->isRejected());

        $body = $this->lastRequestBody();

        // Los tipos compartidos del WSDL viven en apps.services.soap.core.views.
        self::assertStringContainsString('<tns:UUIDS_AR>', $body);
        self::assertStringContainsString('<s0:uuids_ar xmlns:s0="apps.services.soap.core.views">', $body);
        self::assertStringContainsString('<s0:respuesta>Aceptacion</s0:respuesta>', $body);
        self::assertStringContainsString('<s0:respuesta>Rechazo</s0:respuesta>', $body);
        self::assertStringContainsString('<tns:rtaxpayer_id>EKU9003173C9</tns:rtaxpayer_id>', $body);
    }

    public function testRechazaAceptarSinRespuestas(): void
    {
        $service = $this->cancelService([]);

        $this->expectException(ValidationException::class);

        $service->acceptReject([]);
    }

    public function testConsultaElEstatusDeUnCfdiVigente(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('get-sat-status-active'))]);

        $status = $service->getSatStatus(self::UUID_A, total: '1160.00');

        self::assertTrue($status->isSuccess());
        self::assertTrue($status->isActive());
        self::assertFalse($status->isCancelled());
        self::assertTrue($status->isCancellable());
        self::assertTrue($status->details?->requiresReceiverAcceptance());
        self::assertFalse($status->details?->isListedAsEfos());

        $body = $this->lastRequestBody();

        self::assertStringContainsString('<tns:get_sat_status', $body);
        self::assertStringContainsString('<tns:taxpayer_id>EKU9003173C9</tns:taxpayer_id>', $body);
        self::assertStringContainsString('<tns:total>1160.00</tns:total>', $body);
    }

    public function testConsultaElEstatusDeUnCfdiYaCancelado(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('get-sat-status-cancelled'))]);

        $status = $service->getSatStatus(self::UUID_A);

        self::assertTrue($status->isCancelled());
        self::assertFalse($status->isActive());
        self::assertFalse($status->isCancellable());
        self::assertSame('No cancelable', $status->details?->cancellable);
    }

    public function testUnEstatusNoEncontradoNoEsExito(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('get-sat-status-not-found'))]);

        $status = $service->getSatStatus(self::UUID_A);

        self::assertFalse($status->isSuccess());
        self::assertFalse($status->details?->isFound());
        self::assertStringContainsString('N 601', (string) $status->getStatusCode());
    }

    public function testTomaLosDatosDelCfdiParaConsultarElEstatusYEvitarElErrorN601(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('get-sat-status-active'))]);

        $status = $service->getStatusOf(Fixtures::stampedCfdi());

        self::assertTrue($status->isSuccess());

        $body = $this->lastRequestBody();

        self::assertStringContainsString('<tns:uuid>7D162D12-F6B6-4BDE-BC8A-BABC4331919A</tns:uuid>', $body);
        self::assertStringContainsString('<tns:taxpayer_id>EKU9003173C9</tns:taxpayer_id>', $body);
        self::assertStringContainsString('<tns:rtaxpayer_id>MISC491214B86</tns:rtaxpayer_id>', $body);
        self::assertStringContainsString('<tns:total>1160.00</tns:total>', $body);
    }

    public function testNoSePuedeConsultarElEstatusDeUnCfdiSinTimbre(): void
    {
        $service = $this->cancelService([]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/TimbreFiscalDigital/');

        $service->getStatusOf(Fixtures::signedCfdi());
    }

    public function testListaLasCancelacionesPendientesDeUnReceptor(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('get-pending'))]);

        $pending = $service->getPending();

        self::assertTrue($pending->isSuccess());
        self::assertSame(2, $pending->count());
        self::assertTrue($pending->contains('7D162D12-F6B6-4BDE-BC8A-BABC4331919A'));
        self::assertFalse($pending->contains('00000000-0000-0000-0000-000000000000'));
        self::assertStringContainsString('<tns:rtaxpayer_id>EKU9003173C9</tns:rtaxpayer_id>', $this->lastRequestBody());
    }

    public function testSoportaLaVarianteDeRespuestaConVariosNodosUuids(): void
    {
        $variante = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<senv:Envelope xmlns:senv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="http://facturacion.finkok.com/cancel" xmlns:s0="apps.services.soap.core.views">'
            .'<senv:Body><tns:get_pendingResponse><tns:get_pendingResult>'
            .'<s0:uuids>UUID-UNO</s0:uuids><s0:uuids>UUID-DOS</s0:uuids>'
            .'</tns:get_pendingResult></tns:get_pendingResponse></senv:Body></senv:Envelope>';

        $service = $this->cancelService([new MockResponse($variante)]);

        $pending = $service->getPending();

        self::assertSame(['UUID-UNO', 'UUID-DOS'], $pending->uuids);
        self::assertTrue($pending->isNotEmpty());
    }

    public function testRecuperaElAcuseDeUnUuid(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('get-receipt'))]);

        $acknowledgment = $service->getReceipt('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', ReceiptType::Reception);

        self::assertTrue($acknowledgment->isSuccess());
        self::assertTrue($acknowledgment->hasReceipt());
        self::assertStringContainsString('<Acuse', (string) $acknowledgment->receipt);
        self::assertSame('2024-05-21T11:05:00', $acknowledgment->date);

        $body = $this->lastRequestBody();

        self::assertStringContainsString('<tns:type>I</tns:type>', $body);
        self::assertStringContainsString('<tns:taxpayer_id>EKU9003173C9</tns:taxpayer_id>', $body);
    }

    public function testElTipoDeAcuseSePuedeCambiarACancelacion(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('get-receipt'))]);

        $service->getReceipt('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', ReceiptType::Cancellation);

        self::assertStringContainsString('<tns:type>C</tns:type>', $this->lastRequestBody());
    }

    public function testConsultaElEstadoDeUnaCancelacionEnBuffer(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('query-pending'))]);

        $result = $service->queryPendingCancellation(self::UUID_A);

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->isStampedNotSent());
        self::assertStringContainsString('<tns:query_pending_cancellation', $this->lastRequestBody());
        self::assertStringContainsString('<tns:uuid>'.self::UUID_A.'</tns:uuid>', $this->lastRequestBody());
    }
}
