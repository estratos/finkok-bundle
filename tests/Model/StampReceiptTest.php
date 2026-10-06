<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Model;

use Estratos\FinkokBundle\Exception\ApiException;
use Estratos\FinkokBundle\Model\ErrorCode;
use Estratos\FinkokBundle\Model\Incidence;
use Estratos\FinkokBundle\Model\IncidenceCollection;
use Estratos\FinkokBundle\Model\StampReceipt;
use PHPUnit\Framework\TestCase;

final class StampReceiptTest extends TestCase
{
    private function receipt(
        ?string $uuid = null,
        ?string $xml = null,
        ?string $status = null,
        ?IncidenceCollection $incidences = null,
    ): StampReceipt {
        return new StampReceipt(
            uuid: $uuid,
            xml: $xml,
            status: $status,
            incidences: $incidences ?? IncidenceCollection::empty(),
        );
    }

    public function testUnTimbradoNuevoEsExitosoYSeReconoceComoReciente(): void
    {
        $receipt = $this->receipt(
            uuid: '7D162D12-F6B6-4BDE-BC8A-BABC4331919A',
            xml: '<cfdi:Comprobante/>',
            status: StampReceipt::STATUS_STAMPED,
        );

        self::assertTrue($receipt->isSuccess());
        self::assertTrue($receipt->isFreshStamp());
        self::assertFalse($receipt->isPreviouslyStamped());
        self::assertTrue($receipt->hasStampedXml());
        self::assertSame('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', $receipt->getUuid());
        self::assertNull($receipt->getErrorMessage());
    }

    public function testUn307SeReconoceComoTimbrePrevioAunqueElEstadoNoCoincida(): void
    {
        $incidences = new IncidenceCollection([
            new Incidence(code: '307', message: 'El CFDI contiene un timbre previo'),
        ]);

        $receipt = $this->receipt(
            uuid: '7D162D12-F6B6-4BDE-BC8A-BABC4331919A',
            xml: '<cfdi:Comprobante/>',
            status: 'Comprobante timbrado previamente',
            incidences: $incidences,
        );

        self::assertTrue($receipt->isSuccess(), 'El CFDI está timbrado, así que la operación es utilizable.');
        self::assertTrue($receipt->isPreviouslyStamped());
        self::assertFalse($receipt->isFreshStamp());
        self::assertTrue($receipt->hasErrorCode(ErrorCode::AlreadyStamped));
    }

    public function testUnTimbradoPrevioSinXmlTodaviaNoEsRecuperable(): void
    {
        $receipt = $this->receipt(
            uuid: '7D162D12-F6B6-4BDE-BC8A-BABC4331919A',
            xml: null,
            status: 'Comprobante timbrado previamente',
        );

        self::assertTrue($receipt->isSuccess());
        self::assertFalse($receipt->hasStampedXml());
        self::assertNull($receipt->getStampedXml());
    }

    public function testUnaIncidencia705NoEsExitosaYDescribeLaSolucion(): void
    {
        $receipt = $this->receipt(
            status: null,
            incidences: new IncidenceCollection([
                new Incidence(
                    id: 'ID_incidencia',
                    code: '705',
                    message: 'XML Estructura inválida',
                    workProcessId: 'WorkProcessId',
                ),
            ]),
        );

        self::assertFalse($receipt->isSuccess());
        self::assertSame(['705'], $receipt->getErrorCodes());
        self::assertStringContainsString('XML Estructura inválida', (string) $receipt->getErrorMessage());
        // El mensaje se conserva tal cual lo devuelve Finkok; la pista de solución
        // viaja en la incidencia y en el mensaje de la ApiException.
        self::assertSame('XML Estructura inválida', $receipt->getErrorMessage());
        self::assertStringContainsString('schemaLocation', (string) $receipt->getIncidences()->first()?->hint());
    }

    public function testLaApiExceptionIncluyeLaPistaDeSolucionDelCodigo301(): void
    {
        $receipt = $this->receipt(
            incidences: new IncidenceCollection([new Incidence(code: '301', message: 'XML mal formado')]),
        );

        try {
            $receipt->assertSuccess();
            self::fail('Se esperaba ApiException.');
        } catch (ApiException $exception) {
            self::assertTrue($exception->hasErrorCode(ErrorCode::MalformedXml));
            self::assertStringContainsString('validador.finkok.com', $exception->getMessage());
            self::assertStringContainsString('[301] XML mal formado', $exception->getMessage());
        }
    }

    public function testAssertSuccessLanzaApiExceptionConLasIncidencias(): void
    {
        $receipt = $this->receipt(
            incidences: new IncidenceCollection([new Incidence(code: '300', message: 'El usuario o contraseña son inválidos')]),
        );

        try {
            $receipt->assertSuccess();
            self::fail('Se esperaba ApiException.');
        } catch (ApiException $exception) {
            self::assertSame(['300'], $exception->getErrorCodes());
            self::assertTrue($exception->hasErrorCode(ErrorCode::InvalidCredentials));
            self::assertSame($receipt, $exception->getResult());
            self::assertStringContainsString('usuario o contraseña', $exception->getMessage());
        }
    }

    public function testAssertSuccessDevuelveElMismoObjetoCuandoTodoVaBien(): void
    {
        $receipt = $this->receipt(uuid: '7D162D12-F6B6-4BDE-BC8A-BABC4331919A', xml: '<x/>', status: StampReceipt::STATUS_STAMPED);

        self::assertSame($receipt, $receipt->assertSuccess());
    }

    public function testUnFaultComoDatoNoSeConsideraExito(): void
    {
        $receipt = new StampReceipt(
            uuid: '7D162D12-F6B6-4BDE-BC8A-BABC4331919A',
            faultCode: 'soap:Server',
            faultString: 'Error interno',
        );

        self::assertTrue($receipt->hasFault());
        self::assertFalse($receipt->isSuccess());
    }

    public function testExtraeElUuidDelXmlTimbradoComoVerificacionCruzada(): void
    {
        $receipt = new StampReceipt(
            uuid: '7D162D12-F6B6-4BDE-BC8A-BABC4331919A',
            xml: \Estratos\FinkokBundle\Tests\Fixtures::stampedCfdi(),
        );

        self::assertSame('7D162D12-F6B6-4BDE-BC8A-BABC4331919A', $receipt->uuidFromXml());
    }

    public function testUuidFromXmlEsNullCuandoNoHayXmlOEstaMalFormado(): void
    {
        self::assertNull((new StampReceipt(uuid: 'X'))->uuidFromXml());
        self::assertNull((new StampReceipt(uuid: 'X', xml: '<sin cerrar'))->uuidFromXml());
        self::assertNull((new StampReceipt(uuid: 'X', xml: '<cfdi:Comprobante/>'))->uuidFromXml());
    }

    public function testDescribeResumeElResultadoParaLogs(): void
    {
        $receipt = $this->receipt(
            uuid: '7D162D12-F6B6-4BDE-BC8A-BABC4331919A',
            status: StampReceipt::STATUS_STAMPED,
        );

        self::assertStringContainsString('OK', $receipt->describe());
        self::assertStringContainsString(StampReceipt::STATUS_STAMPED, $receipt->describe());
    }
}
