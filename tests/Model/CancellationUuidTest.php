<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Tests\Model;

use Finkok\CfdiBundle\Exception\ValidationException;
use Finkok\CfdiBundle\Model\AcceptRejectAnswer;
use Finkok\CfdiBundle\Model\CancellationReason;
use Finkok\CfdiBundle\Model\CancellationUuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CancellationUuidTest extends TestCase
{
    public function testRepresentaUnUuidSinMotivo(): void
    {
        $uuid = new CancellationUuid('A1B2C3D4-1111-2222-3333-444455556666');

        self::assertFalse($uuid->hasReason());
        self::assertFalse($uuid->hasReplacementFolio());
        self::assertSame('A1B2C3D4-1111-2222-3333-444455556666', (string) $uuid);
    }

    public function testElMotivo01ExigeElFolioDeSustitucion(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/exige indicar el UUID del comprobante que sustituye/');

        new CancellationUuid('A1B2C3D4-1111-2222-3333-444455556666', CancellationReason::ErrorsWithRelation);
    }

    public function testElMotivo01ConFolioDeSustitucionEsValido(): void
    {
        $uuid = new CancellationUuid(
            'A1B2C3D4-1111-2222-3333-444455556666',
            CancellationReason::ErrorsWithRelation,
            'B2C3D4E5-1111-2222-3333-444455556666',
        );

        self::assertTrue($uuid->hasReplacementFolio());
        self::assertSame('B2C3D4E5-1111-2222-3333-444455556666', $uuid->replacementFolio);
    }

    /**
     * @return iterable<string, array{CancellationReason}>
     */
    public static function motivosSinFolioDeSustitucion(): iterable
    {
        yield '02 sin relación' => [CancellationReason::ErrorsWithoutRelation];
        yield '03 no se llevó a cabo' => [CancellationReason::OperationNotCarriedOut];
        yield '04 factura global' => [CancellationReason::NominativeGlobalInvoice];
    }

    #[DataProvider('motivosSinFolioDeSustitucion')]
    public function testLosMotivos02a04NoAdmitenFolioDeSustitucion(CancellationReason $reason): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/no permite indicar FolioSustitucion/');

        new CancellationUuid('A1B2C3D4-1111-2222-3333-444455556666', $reason, 'B2C3D4E5-1111-2222-3333-444455556666');
    }

    #[DataProvider('motivosSinFolioDeSustitucion')]
    public function testLosMotivos02a04SinFolioSonValidos(CancellationReason $reason): void
    {
        $uuid = new CancellationUuid('A1B2C3D4-1111-2222-3333-444455556666', $reason);

        self::assertTrue($uuid->hasReason());
        self::assertSame($reason, $uuid->reason);
    }

    public function testRechazaUnUuidVacio(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/"uuid" es obligatorio/');

        new CancellationUuid('   ');
    }

    public function testNormalizaListasDeUuidYDeObjetos(): void
    {
        $list = CancellationUuid::normalizeList([
            'A1B2C3D4-1111-2222-3333-444455556666',
            new CancellationUuid('B2C3D4E5-1111-2222-3333-444455556666', CancellationReason::OperationNotCarriedOut),
        ]);

        self::assertCount(2, $list);
        self::assertInstanceOf(CancellationUuid::class, $list[0]);
        self::assertSame(CancellationReason::OperationNotCarriedOut, $list[1]->reason);

        self::assertSame(
            ['A1B2C3D4-1111-2222-3333-444455556666', 'B2C3D4E5-1111-2222-3333-444455556666'],
            CancellationUuid::toUuidList($list),
        );
    }

    public function testValidaElFormatoDelFolioFiscal(): void
    {
        self::assertTrue(CancellationUuid::isValidUuid('A1B2C3D4-1111-2222-3333-444455556666'));
        self::assertFalse(CancellationUuid::isValidUuid('A1B2C3D4-1111-2222-3333-44445555666'));
        self::assertFalse(CancellationUuid::isValidUuid('no-es-un-uuid'));
    }

    public function testLaRespuestaDeAceptacionSePuedeInterpretarDesdeLaNotacionDelSat(): void
    {
        self::assertSame(AcceptRejectAnswer::Accepted, AcceptRejectAnswer::fromSat('A'));
        self::assertSame(AcceptRejectAnswer::Rejected, AcceptRejectAnswer::fromSat('R'));
        self::assertSame(AcceptRejectAnswer::Accepted, AcceptRejectAnswer::fromSat('aceptacion'));
        self::assertTrue(AcceptRejectAnswer::Accepted->isAccepted());
        self::assertFalse(AcceptRejectAnswer::Rejected->isAccepted());
    }

    public function testUnaRespuestaDesconocidaEsUnErrorExplicito(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Se esperaba "A" \(aceptar\) o "R" \(rechazar\)/');

        AcceptRejectAnswer::fromSat('quizá');
    }

    public function testElMotivoDeCancelacionSeDescribeParaCatalogos(): void
    {
        self::assertSame('01 - Comprobante emitido con errores con relación.', CancellationReason::ErrorsWithRelation->label());
        self::assertTrue(CancellationReason::ErrorsWithRelation->requiresReplacementFolio());
        self::assertFalse(CancellationReason::OperationNotCarriedOut->requiresReplacementFolio());
    }
}
