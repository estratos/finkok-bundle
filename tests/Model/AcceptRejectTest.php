<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Model;

use Estratos\FinkokBundle\Model\AcceptRejectEntry;
use Estratos\FinkokBundle\Model\AcceptRejectStatus;
use PHPUnit\Framework\TestCase;

/**
 * El método accept_reject responde con los códigos 1000-1006, no con el rango
 * 201-212 del método cancel.
 */
final class AcceptRejectTest extends TestCase
{
    public function testResuelveTodosLosCodigosDocumentados(): void
    {
        $descripciones = [
            '1000' => 'Se recibió la respuesta de la petición de forma exitosa.',
            '1001' => 'No existen peticiones de cancelación en espera de respuesta para el UUID.',
            '1002' => 'Ya se recibió una respuesta para la petición de cancelación del UUID.',
            '1003' => 'El sello no corresponde al RFC del receptor.',
            '1004' => 'Existen más de una petición de cancelación para el mismo UUID.',
            '1005' => 'El UUID es nulo o no posee el formato correcto.',
            '1006' => 'Se rebasó el número máximo de solicitudes permitidas.',
        ];

        foreach ($descripciones as $valor => $descripcion) {
            // Las claves numéricas de un arreglo PHP son enteros: hay que convertir.'
            $code = AcceptRejectStatus::tryFrom((string) $valor);

            self::assertNotNull($code, sprintf('Falta el código %s.', $valor));
            self::assertSame($descripcion, $code->description());
            self::assertNotSame('', $code->hint());
        }
    }

    public function testSoloElCodigo1000EsExito(): void
    {
        self::assertTrue(AcceptRejectStatus::ResponseReceived->isSuccess());

        foreach (AcceptRejectStatus::cases() as $code) {
            if (AcceptRejectStatus::ResponseReceived !== $code) {
                self::assertFalse($code->isSuccess(), sprintf('El código %s no es un éxito.', $code->value));
            }
        }
    }

    public function testLosCodigos1001Y1002IndicanQueNoHabiaNadaQueResponder(): void
    {
        self::assertTrue(AcceptRejectStatus::NoPendingRequests->isNothingToAnswer());
        self::assertTrue(AcceptRejectStatus::AlreadyAnswered->isNothingToAnswer());
        self::assertFalse(AcceptRejectStatus::ResponseReceived->isNothingToAnswer());
        self::assertFalse(AcceptRejectStatus::SealDoesNotMatchReceiver->isNothingToAnswer());
    }

    public function testElCodigo1006RequiereIntervencionDeSoporte(): void
    {
        self::assertTrue(AcceptRejectStatus::MaxRequestsExceeded->requiresManualAction());
        self::assertFalse(AcceptRejectStatus::ResponseReceived->requiresManualAction());
    }

    public function testUnaEntradaConCodigo1000EstaConfirmada(): void
    {
        $entry = new AcceptRejectEntry(
            uuid: '7D162D12-F6B6-4BDE-BC8A-BABC4331919A',
            status: '1000',
            accepted: true,
        );

        self::assertTrue($entry->isAccepted());
        self::assertFalse($entry->isRejected());
        self::assertTrue($entry->isAcknowledged());
        self::assertFalse($entry->isNothingToAnswer());
        self::assertSame(AcceptRejectStatus::ResponseReceived, $entry->statusCode());
        self::assertStringContainsString('aceptado', (string) $entry);
    }

    /**
     * Regresión: la implementación anterior daba por buena cualquier respuesta del
     * rango 2xx, así que un 1000 real se reportaba como no confirmado.
     */
    public function testUnCodigoDelRango2xxNoSeConsideraConfirmado(): void
    {
        $entry = new AcceptRejectEntry(uuid: 'A1B2C3D4-1111-2222-3333-444455556666', status: '201');

        self::assertNull($entry->statusCode(), 'El rango 201 es del método cancel, no de accept_reject.');
        self::assertFalse($entry->isAcknowledged());
        self::assertNull($entry->getStatusDescription());
    }

    public function testUnaEntradaSinStatusNoEstaConfirmada(): void
    {
        $entry = new AcceptRejectEntry(uuid: 'A1B2C3D4-1111-2222-3333-444455556666');

        self::assertNull($entry->statusCode());
        self::assertFalse($entry->isAcknowledged());
        self::assertFalse($entry->isNothingToAnswer());
        self::assertTrue($entry->isRejected());
    }

    public function testUnaEntradaSinPeticionesPendientesSeReconoce(): void
    {
        $entry = new AcceptRejectEntry(uuid: 'A1B2C3D4-1111-2222-3333-444455556666', status: '1001', accepted: true);

        self::assertFalse($entry->isAcknowledged());
        self::assertTrue($entry->isNothingToAnswer());
        self::assertStringContainsString('no hay nada que aceptar', (string) $entry->getHint());
    }
}