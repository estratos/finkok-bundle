<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Tests\Model;

use Finkok\CfdiBundle\Model\ErrorCode;
use Finkok\CfdiBundle\Model\Incidence;
use Finkok\CfdiBundle\Model\IncidenceCollection;
use PHPUnit\Framework\TestCase;

final class IncidenceCollectionTest extends TestCase
{
    private function collection(): IncidenceCollection
    {
        return new IncidenceCollection([
            new Incidence(id: '1', code: '300', message: 'El usuario o contraseña son inválidos'),
            new Incidence(id: '2', code: '307', message: 'El CFDI contiene un timbre previo', uuid: 'A1B2C3D4-1111-2222-3333-444455556666'),
            new Incidence(id: '3', code: '709', message: 'SelloSat no pudo ser creado'),
        ]);
    }

    public function testEsContableYRecorrible(): void
    {
        $collection = $this->collection();

        self::assertCount(3, $collection);
        self::assertFalse($collection->isEmpty());
        self::assertTrue($collection->isNotEmpty());
        self::assertSame('300', $collection->first()?->code);
        self::assertSame('709', $collection->last()?->code);

        $codes = [];
        foreach ($collection as $incidence) {
            $codes[] = $incidence->code;
        }

        self::assertSame(['300', '307', '709'], $codes);
    }

    public function testExponeLosCodigosYLosTipificados(): void
    {
        $collection = $this->collection();

        self::assertSame(['300', '307', '709'], $collection->codes());
        self::assertSame(
            [ErrorCode::InvalidCredentials, ErrorCode::AlreadyStamped, ErrorCode::SatSealCouldNotBeCreated],
            $collection->errorCodes(),
        );
        self::assertTrue($collection->hasCode('307'));
        self::assertTrue($collection->hasErrorCode(ErrorCode::AlreadyStamped));
        self::assertFalse($collection->hasErrorCode(ErrorCode::InvalidXmlStructure));
    }

    public function testUnCodigoNoDocumentadoNoSeTipificaPeroSiSeReporta(): void
    {
        $collection = new IncidenceCollection([new Incidence(code: '9999', message: 'Código nuevo')]);

        self::assertSame(['9999'], $collection->codes());
        self::assertSame([], $collection->errorCodes());
        self::assertNull($collection->first()?->errorCode());
        self::assertNull($collection->first()?->hint());
    }

    public function testSeparaLasIncidenciasTransitoriasDeLasQueExigenAccionManual(): void
    {
        $collection = $this->collection();

        // 307 (timbre previo: Finkok ya recuperó el XML) y 709 (balanceo en DEMO)
        // son reintentables; ninguna es de las que exigen intervención manual.
        self::assertSame(['307', '709'], $collection->transient()->codes());
        self::assertSame([], $collection->requiringManualAction()->codes());
        self::assertTrue($collection->transient()->isNotEmpty());

        $manual = new IncidenceCollection([
            new Incidence(code: '703', message: 'Cuenta suspendida'),
            new Incidence(code: '718', message: 'Timbres agotados'),
        ]);

        self::assertSame(['703', '718'], $manual->requiringManualAction()->codes());
        self::assertTrue($manual->transient()->isEmpty());
    }

    public function testConstruyeElDescribeConMensajeYUuid(): void
    {
        $collection = $this->collection();

        $description = $collection->describe();

        self::assertStringContainsString('[300] El usuario o contraseña son inválidos', $description);
        self::assertStringContainsString('UUID: A1B2C3D4-1111-2222-3333-444455556666', $description);
    }

    public function testUnaColeccionVaciaNoProduceTexto(): void
    {
        $collection = IncidenceCollection::empty();

        self::assertSame('', $collection->describe());
        self::assertSame('', (string) $collection);
        self::assertNull($collection->first());
        self::assertNull($collection->last());
    }

    public function testFiltraConUnPredicadoPropio(): void
    {
        $soloUuid = $this->collection()->filter(
            static fn (Incidence $incidence): bool => null !== $incidence->uuid,
        );

        self::assertSame(['307'], $soloUuid->codes());
    }

    public function testCadaCodigoDocumentadoTieneDescripcionYPista(): void
    {
        foreach (ErrorCode::cases() as $code) {
            self::assertNotSame('', $code->description(), sprintf('El código %s debe tener descripción.', $code->value));
            self::assertNotSame('', $code->hint(), sprintf('El código %s debe tener pista de solución.', $code->value));
        }
    }

    public function testInterpretaLosCodigosSinDistinguirMayusculas(): void
    {
        self::assertSame(ErrorCode::CfdiDigestMismatch, ErrorCode::tryFrom('CFDI40102'));
        self::assertSame(ErrorCode::InvalidXmlStructure, ErrorCode::tryFrom('705'));

        $incidence = new Incidence(code: 'cfdi40102');
        self::assertSame(ErrorCode::CfdiDigestMismatch, $incidence->errorCode());
    }

    public function testClasificaLosCodigosDeNegocioMasRelevantes(): void
    {
        self::assertTrue(ErrorCode::AlreadyStamped->isAlreadyStamped());
        self::assertTrue(ErrorCode::ExistingStamp->isAlreadyStamped());
        self::assertTrue(ErrorCode::SatSealCouldNotBeCreated->isTransient());
        self::assertTrue(ErrorCode::StampsExhausted->requiresManualAction());
        self::assertFalse(ErrorCode::InvalidXmlStructure->requiresManualAction());
        self::assertFalse(ErrorCode::InvalidCredentials->isTransient());
    }

    public function testElTextoDeUnaIncidenciaIncluyeLaSugerencia(): void
    {
        $incidence = new Incidence(code: '705', message: 'XML Estructura inválida');

        self::assertStringContainsString('[705] XML Estructura inválida', $incidence->describe());
        self::assertStringContainsString('schemaLocation', $incidence->describe());
        self::assertStringContainsString('Sugerencia:', $incidence->describe());
    }
}
