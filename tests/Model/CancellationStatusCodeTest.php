<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Model;

use Estratos\FinkokBundle\Model\CancellationStatusCode;
use PHPUnit\Framework\TestCase;

/**
 * Catálogo completo de códigos de cancelación según la documentación de Finkok:
 * validación de la cancelación (201-212 y no_cancelable), validaciones de petición
 * (300-314) y errores propios de Finkok (704, 708, 711, 798, 799).
 */
final class CancellationStatusCodeTest extends TestCase
{
    public function testCubreTodosLosCodigosDocumentados(): void
    {
        $documentados = [
            '201', '202', '203', '204', '205', '206', '207', '208', '209', '210', '211', '212',
            'no_cancelable',
            '300', '301', '302', '304', '305', '309', '310', '311', '312', '314',
            '704', '708', '711', '798', '799',
        ];

        foreach ($documentados as $valor) {
            self::assertNotNull(
                CancellationStatusCode::tryFrom($valor),
                sprintf('Falta el código %s documentado por Finkok.', $valor),
            );
        }

        self::assertCount(count($documentados), CancellationStatusCode::cases());
    }

    public function testTodosLosCodigosTienenDescripcionYPista(): void
    {
        foreach (CancellationStatusCode::cases() as $code) {
            self::assertNotSame('', $code->description(), sprintf('El código %s necesita descripción.', $code->value));
            self::assertNotSame('', $code->hint(), sprintf('El código %s necesita pista.', $code->value));
        }
    }

    public function testSolo201202Y798IndicanQueLaPeticionFueRecibida(): void
    {
        self::assertTrue(CancellationStatusCode::RequestAccepted->isRequestAccepted());
        self::assertTrue(CancellationStatusCode::PreviouslyCancelled->isRequestAccepted());
        self::assertTrue(CancellationStatusCode::AlreadyRequested->isRequestAccepted());

        foreach (CancellationStatusCode::cases() as $code) {
            if (!in_array($code, [
                CancellationStatusCode::RequestAccepted,
                CancellationStatusCode::PreviouslyCancelled,
                CancellationStatusCode::AlreadyRequested,
            ], true)) {
                self::assertFalse($code->isRequestAccepted(), sprintf('El código %s no acepta la petición.', $code->value));
            }
        }
    }

    public function testElRango203a212SonRechazosDelUuid(): void
    {
        foreach (['203', '204', '205', '206', '207', '208', '209', '210', '211', '212', 'no_cancelable'] as $valor) {
            $code = CancellationStatusCode::tryFrom($valor);
            self::assertNotNull($code);
            self::assertTrue($code->isRejection(), sprintf('El código %s debería ser un rechazo.', $valor));
        }

        self::assertFalse(CancellationStatusCode::RequestAccepted->isRejection());
        self::assertFalse(CancellationStatusCode::SatUnreachable->isRejection(), '708 es un fallo de Finkok, no del UUID.');
    }

    public function testDistingueLosCodigosDeUuidInexistenteODelEmisorEquivocado(): void
    {
        self::assertTrue(CancellationStatusCode::NotFoundOrIssuerMismatch->isNotFound());
        self::assertTrue(CancellationStatusCode::UuidNotFound->isNotFound());
        self::assertFalse(CancellationStatusCode::NotApplicable->isNotFound());
    }

    public function testMarcaComoTransitoriosLosQueConvieneReintentar(): void
    {
        self::assertTrue(CancellationStatusCode::UuidNotFound->isTransient());
        self::assertTrue(CancellationStatusCode::MalformedSeal->isTransient(), 'Finkok sugiere reintentar por intermitencia del SAT.');
        self::assertTrue(CancellationStatusCode::SatUnreachable->isTransient());
        self::assertTrue(CancellationStatusCode::AlreadyRequested->isTransient());
        self::assertFalse(CancellationStatusCode::InvalidReason->isTransient());
    }

    public function testElCodigo300EsUnFalloDeCredenciales(): void
    {
        self::assertTrue(CancellationStatusCode::InvalidUser->isCredentialError());
        self::assertFalse(CancellationStatusCode::InvalidUser->isTransient());
        self::assertFalse(CancellationStatusCode::InvalidReason->isCredentialError());
    }

    public function testLosFallasDeCodificacionDelCsdSeDistinguen(): void
    {
        self::assertTrue(CancellationStatusCode::DoubleBase64Encoding->isConfigurationError());
        self::assertTrue(CancellationStatusCode::CertificateError->isConfigurationError());
        self::assertFalse(CancellationStatusCode::InvalidUser->isConfigurationError());
    }

    public function testEl201SoloConfirmaLaPeticionSegunLaDocumentacion(): void
    {
        $code = CancellationStatusCode::RequestAccepted;

        self::assertTrue($code->isRequestAccepted());
        self::assertStringContainsString('Petición de cancelación realizada exitosamente', $code->description());
        self::assertStringContainsString('Confirma la cancelación definitiva', $code->hint());
    }
}