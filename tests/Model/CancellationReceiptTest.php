<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Model;

use Estratos\FinkokBundle\Exception\ApiException;
use Estratos\FinkokBundle\Model\CancellationFolio;
use Estratos\FinkokBundle\Model\CancellationReceipt;
use Estratos\FinkokBundle\Model\CancellationStatusCode;
use Estratos\FinkokBundle\Model\ErrorCode;
use Estratos\FinkokBundle\Model\Incidence;
use Estratos\FinkokBundle\Model\IncidenceCollection;
use PHPUnit\Framework\TestCase;

final class CancellationReceiptTest extends TestCase
{
    public function testUnFolio202EsUnaCancelacionExitosa(): void
    {
        $receipt = new CancellationReceipt(
            folios: [new CancellationFolio('A1B2C3D4-1111-2222-3333-444455556666', '202', 'Cancelado con aceptación')],
            status: '201',
        );

        self::assertTrue($receipt->isSuccess());
        self::assertTrue($receipt->folios[0]->isCancelled());
        self::assertTrue($receipt->folios[0]->requiresReceiverAcceptance());
        self::assertSame(['A1B2C3D4-1111-2222-3333-444455556666'], $receipt->cancelledUuids());
        self::assertFalse($receipt->isInProcess());
        self::assertSame(CancellationStatusCode::RequestAccepted, $receipt->cancellationStatusCode());
    }

    public function testElEnProcesoSeLeeDeEstatusCancelacionNoDelCodigo(): void
    {
        // Finkok devuelve 201 (petición realizada) junto al texto «En proceso»:
        // la cancelación todavía no es definitiva.
        $receipt = new CancellationReceipt(
            folios: [new CancellationFolio('A1B2C3D4-1111-2222-3333-444455556666', '201', 'En proceso')],
            status: '201',
        );

        $folio = $receipt->folios[0];

        self::assertTrue($receipt->isSuccess(), 'La petición sí fue aceptada.');
        self::assertTrue($receipt->isInProcess());
        self::assertTrue($folio->isRequestAccepted());
        self::assertFalse($folio->isCancelled(), 'En proceso no es cancelado.');
        self::assertSame(['A1B2C3D4-1111-2222-3333-444455556666'], $receipt->pendingUuids());
        self::assertSame(['A1B2C3D4-1111-2222-3333-444455556666'], $receipt->acceptedUuids());
        self::assertSame([], $receipt->cancelledUuids());
    }

    public function testUnFolio205ReportaQueElUuidNoExiste(): void
    {
        $folio = new CancellationFolio('A1B2C3D4-1111-2222-3333-444455556666', '205');

        self::assertTrue($folio->isNotFound());
        self::assertTrue($folio->isRejected());
        self::assertFalse($folio->isInProcess(), '205 no significa «en proceso».');
        self::assertTrue($folio->statusCode()?->isTransient());
    }

    public function testUn201SinEstatusTextualNoSeAsumeComoCancelado(): void
    {
        // Advertencia explícita de Finkok: el 201 confirma la petición, no la
        // cancelación. Sin el texto del SAT no se puede afirmar que se canceló.
        $folio = new CancellationFolio('A1B2C3D4-1111-2222-3333-444455556666', '201');

        self::assertTrue($folio->isRequestAccepted());
        self::assertFalse($folio->isCancelled());
        self::assertFalse($folio->isInProcess());
    }

    public function testUn202SiEsCancelacionDefinitiva(): void
    {
        $folio = new CancellationFolio('A1B2C3D4-1111-2222-3333-444455556666', '202');

        self::assertTrue($folio->isCancelled(), '202 = UUID previamente cancelado.');
        self::assertTrue($folio->isRequestAccepted());
    }

    public function testUnFolio204SeReportaComoNoCancelableYRechazado(): void
    {
        // 204 = «UUID no aplicable para cancelación»; el texto acompaña al código.
        $receipt = new CancellationReceipt(
            folios: [new CancellationFolio('A1B2C3D4-1111-2222-3333-444455556666', '204', 'No cancelable')],
            status: '204',
        );

        self::assertFalse($receipt->isSuccess());
        self::assertTrue($receipt->folios[0]->isNotCancellable());
        self::assertTrue($receipt->folios[0]->isRejected());
        self::assertSame(['A1B2C3D4-1111-2222-3333-444455556666'], $receipt->rejectedUuids());
        self::assertSame(CancellationStatusCode::NotApplicable, $receipt->cancellationStatusCode());
    }

    public function testUnFolio203IndicaQueNoSeEncontroElUuidONoEsDelEmisor(): void
    {
        $folio = new CancellationFolio('A1B2C3D4-1111-2222-3333-444455556666', '203');

        self::assertTrue($folio->isNotFound());
        self::assertTrue($folio->isRejected());
        self::assertFalse($folio->isCancelled());
        self::assertFalse($folio->isInProcess());
        self::assertSame('No encontrado o no corresponde en el emisor.', $folio->getStatusDescription());
    }

    public function testElCodigo205DeUuidNoEncontradoNoEsExito(): void
    {
        $receipt = new CancellationReceipt(status: '205');

        self::assertFalse($receipt->isSuccess());
        self::assertSame(CancellationStatusCode::UuidNotFound, $receipt->cancellationStatusCode());
        self::assertTrue($receipt->cancellationStatusCode()?->isTransient());
    }

    public function testElCodigo798IndicaQueYaExistiaLaSolicitud(): void
    {
        $receipt = new CancellationReceipt(status: '798');

        self::assertTrue($receipt->isSuccess(), 'La solicitud previa implica que la cancelación ya se pidió.');
        self::assertSame(CancellationStatusCode::AlreadyRequested, $receipt->cancellationStatusCode());
        self::assertStringContainsStringIgnoringCase('consulta el estatus', (string) $receipt->cancellationStatusCode()?->hint());
    }

    public function testElCodigo799ExigeContactarASoporte(): void
    {
        $receipt = new CancellationReceipt(status: '799');

        self::assertFalse($receipt->isSuccess());
        self::assertTrue($receipt->cancellationStatusCode()?->requiresManualAction());
    }

    public function testElAcuseConfirmaLaCancelacionAunqueNoHayaFolios(): void
    {
        $receipt = new CancellationReceipt(acknowledgment: '<Acuse/>');

        self::assertTrue($receipt->hasAcknowledgment());
        self::assertTrue($receipt->isSuccess());
    }

    public function testLocalizaUnFolioPorSuUuidSinDistinguirMayusculas(): void
    {
        $receipt = new CancellationReceipt(folios: [
            new CancellationFolio('A1B2C3D4-1111-2222-3333-444455556666', '201'),
        ]);

        self::assertNotNull($receipt->getFolio('a1b2c3d4-1111-2222-3333-444455556666'));
        self::assertNull($receipt->getFolio('00000000-0000-0000-0000-000000000000'));
    }

    public function testAssertSuccessLanzaApiExceptionCuandoLaCancelacionFalla(): void
    {
        $receipt = new CancellationReceipt(status: '203');

        try {
            $receipt->assertSuccess();
            self::fail('Se esperaba ApiException.');
        } catch (ApiException $exception) {
            self::assertSame('203', $exception->getStatusCode());
            self::assertStringContainsString('203', $exception->getMessage());
        }
    }

    public function testUnEstadoNoDocumentadoNoSeAsumeComoExito(): void
    {
        $receipt = new CancellationReceipt(status: 'algo-nuevo-de-finkok');

        self::assertNull($receipt->cancellationStatusCode());
        self::assertFalse($receipt->isSuccess());
    }

    public function testUnaIncidenciaDeNegocioSePropagaComoExcepcion(): void
    {
        $receipt = new CancellationReceipt(
            status: '705',
            incidences: new IncidenceCollection([new Incidence(code: '705', message: 'XML Estructura inválida')]),
        );

        try {
            $receipt->assertSuccess();
            self::fail('Se esperaba ApiException.');
        } catch (ApiException $exception) {
            self::assertTrue($exception->hasErrorCode(ErrorCode::InvalidXmlStructure));
        }
    }
}
