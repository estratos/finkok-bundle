<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Tests\Service;

use Finkok\CfdiBundle\Model\ReceiptType;
use Finkok\CfdiBundle\Tests\Concerns\InteractsWithFinkok;
use Finkok\CfdiBundle\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Comportamiento con credenciales inválidas, reproduciendo las respuestas
 * capturadas del ambiente DEMO real de Finkok.
 *
 * El Web Service de cancelación no usa `<Incidencias>` para este caso: responde
 * `CodEstatus` en `cancel` y el nodo `error` en el resto de las operaciones. El
 * bundle debe reportar el motivo en `getErrorMessage()` y en
 * `isCredentialError()` en lugar de dejar el mensaje vacío.
 */
final class CredentialErrorTest extends TestCase
{
    use InteractsWithFinkok;

    private const UUID = '7D162D12-F6B6-4BDE-BC8A-BABC4331919A';

    public function testCancelReportaElCodEstatusEnIngles(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('credentials-error-cancel'))]);

        $receipt = $service->cancel([self::UUID]);

        self::assertFalse($receipt->isSuccess());
        self::assertTrue($receipt->isCredentialError());
        self::assertSame('Invalid Username or Password', $receipt->getStatusCode());
        self::assertSame('Invalid Username or Password', $receipt->getErrorMessage());
        self::assertNull($receipt->cancellationStatusCode(), 'No es un código numérico tipificado.');
    }

    public function testAcceptRejectReportaElErrorEnElNodoError(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('credentials-error-accept-reject'))]);

        $result = $service->acceptReject([self::UUID => 'Aceptacion']);

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isCredentialError());
        self::assertSame('Invalid Username or Password', $result->getErrorMessage());
        self::assertSame(0, $result->countAccepted());
        self::assertSame(0, $result->countRejected());
    }

    public function testGetSatStatusReportaElErrorSinDetalleDelSat(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('credentials-error-get-sat-status'))]);

        $status = $service->getSatStatus(self::UUID);

        self::assertFalse($status->isSuccess());
        self::assertTrue($status->isCredentialError());
        self::assertNull($status->details);
        self::assertSame('Invalid Username or Password', $status->getStatusCode());
    }

    public function testGetPendingReportaElErrorYNoDevuelveUuids(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('credentials-error-get-pending'))]);

        $pending = $service->getPending();

        self::assertFalse($pending->isSuccess());
        self::assertTrue($pending->isCredentialError());
        self::assertTrue($pending->isEmpty());
        self::assertSame('Invalid Username or Password', $pending->getErrorMessage());
    }

    public function testGetReceiptReportaElErrorSinAcuse(): void
    {
        $service = $this->cancelService([new MockResponse(Fixtures::response('credentials-error-get-receipt'))]);

        $acknowledgment = $service->getReceipt(self::UUID, ReceiptType::Cancellation);

        self::assertFalse($acknowledgment->isSuccess());
        self::assertTrue($acknowledgment->isCredentialError());
        self::assertFalse($acknowledgment->hasReceipt());
        self::assertNull($acknowledgment->success);
    }

    public function testQueryPendingCancellationNoConfundeCredencialesConUnUuidFueraDeLaCola(): void
    {
        $service = $this->cancelService([
            new MockResponse(Fixtures::response('credentials-error-query-pending-cancellation')),
        ]);

        $result = $service->queryPendingCancellation(self::UUID);

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isCredentialError());
        self::assertSame('Invalid Username or Password', $result->getStatusCode());
        self::assertSame('Invalid Username or Password', $result->getErrorMessage());
        self::assertFalse($result->isGone());
        self::assertFalse($result->isPending());
    }

    public function testElTimbradoSiUsaLaIncidencia300YPorEsoNoSeConfundeConUnUuidFueraDeLaCola(): void
    {
        $service = $this->stampService([new MockResponse(Fixtures::response('stamp-invalid-xml'))]);

        $receipt = $service->stamp(Fixtures::signedCfdi());

        self::assertFalse($receipt->isCredentialError(), 'La incidencia 705 no es un problema de credenciales.');
    }
}
