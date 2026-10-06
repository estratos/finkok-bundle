<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Integration;

use Estratos\FinkokBundle\Config\Credentials;
use Estratos\FinkokBundle\Config\CredentialsProvider;
use Estratos\FinkokBundle\Config\EndpointResolver;
use Estratos\FinkokBundle\Config\Environment;
use Estratos\FinkokBundle\Exception\SoapFaultException;
use Estratos\FinkokBundle\Model\CancellationReason;
use Estratos\FinkokBundle\Model\CancellationUuid;
use Estratos\FinkokBundle\Model\ErrorCode;
use Estratos\FinkokBundle\Model\ReceiptType;
use Estratos\FinkokBundle\Service\CancelService;
use Estratos\FinkokBundle\Service\StampService;
use Estratos\FinkokBundle\Soap\HttpClientSoapTransport;
use Estratos\FinkokBundle\Tests\Fixtures;
use Estratos\FinkokBundle\Xml\CfdiPreflightValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Prueba de contrato contra el ambiente DEMO real de Finkok.
 *
 * No requiere credenciales válidas: se envían credenciales inexistentes y se
 * verifica que Finkok **entiende** la petición. Es la comprobación más fuerte
 * posible sin una cuenta:
 *
 *  - si el envelope SOAP, el `targetNamespace` o los namespaces de los tipos
 *    complejos estuvieran mal, Finkok respondería un SOAP Fault de
 *    deserialización («Unmarshalling Error», «unexpected element») en lugar de
 *    procesar la operación;
 *  - al recibir la incidencia 300 («El usuario o contraseña son inválidos») o el
 *    `CodEstatus` «Invalid Username or Password» queda demostrado que la petición
 *    se deserializó y llegó al manejador de la operación.
 *
 * Está excluida de la suite por defecto porque depende de la red. Para
 * ejecutarla:
 *
 * ```bash
 * vendor/bin/phpunit --group live
 * ```
 */
#[Group('live')]
final class LiveFinkokDemoTest extends TestCase
{
    private const UUID = '7D162D12-F6B6-4BDE-BC8A-BABC4331919A';

    /** Credenciales deliberadamente inexistentes. */
    private const USER = 'agente-dsh@example.com';
    private const PASSWORD = 'credenciales-de-prueba';
    private const TAXPAYER_ID = 'EKU9003173C9';
    private const RECEIVER_ID = 'MISC491214B86';

    private function credentialsProvider(): CredentialsProvider
    {
        return new CredentialsProvider([
            'sonda' => new Credentials(
                name: 'sonda',
                username: self::USER,
                password: self::PASSWORD,
                taxpayerId: self::TAXPAYER_ID,
                environment: Environment::Demo,
            ),
        ]);
    }

    private function transport(): HttpClientSoapTransport
    {
        return new HttpClientSoapTransport(HttpClient::create(['timeout' => 30]), 30.0);
    }

    private function stampService(): StampService
    {
        return new StampService(
            $this->transport(),
            new EndpointResolver(),
            $this->credentialsProvider(),
            new CfdiPreflightValidator(),
        );
    }

    private function cancelService(): CancelService
    {
        return new CancelService(
            $this->transport(),
            new EndpointResolver(),
            $this->credentialsProvider(),
        );
    }

    public function testLosCuatroMetodosDeTimbradoSonAceptadosPorElServicio(): void
    {
        $service = $this->stampService();
        $cfdi = Fixtures::signedCfdi();

        $resultados = [
            'stamp' => $service->stamp($cfdi),
            'quick_stamp' => $service->quickStamp($cfdi),
            'stamped' => $service->stamped($cfdi),
            'sign_stamp' => $service->signStamp($cfdi),
        ];

        foreach ($resultados as $operacion => $receipt) {
            self::assertFalse(
                $receipt->isSuccess(),
                sprintf('%s: con credenciales inexistentes no puede haber timbrado.', $operacion),
            );
            self::assertFalse($receipt->hasFault(), sprintf('%s: no debe haber SOAP Fault de protocolo.', $operacion));
            self::assertTrue(
                $receipt->hasErrorCode(ErrorCode::InvalidCredentials),
                sprintf(
                    '%s: se esperaba la incidencia 300 (petición entendida, credenciales rechazadas). Incidencias: %s',
                    $operacion,
                    $receipt->getIncidences()->describe() ?: '(ninguna)',
                ),
            );
        }
    }

    public function testQueryPendingLlegaAlManejadorDelServicio(): void
    {
        $service = $this->stampService();

        try {
            $result = $service->queryPending(self::UUID);

            // Si Finkok corrige el fallo descrito abajo, la respuesta normal es la
            // incidencia 300 con credenciales inválidas.
            self::assertFalse($result->isSuccess());
            self::assertTrue($result->getIncidences()->hasErrorCode(ErrorCode::InvalidCredentials));
        } catch (SoapFaultException $exception) {
            // Fallo conocido del servidor de Finkok en DEMO: cuando el usuario no
            // existe, su propia rutina de trazabilidad lanza
            // "local variable 'log_username' referenced before assignment" y lo
            // devuelve como Fault de servidor. Que el Fault mencione el usuario
            // demuestra que la petición se deserializó correctamente.
            self::assertStringContainsString('Server', (string) $exception->getFaultCode());
            self::assertStringContainsString(
                'log_username',
                (string) $exception->getFaultString(),
                'Si este mensaje cambió, Finkok corrigió su fallo: revisa la aserción de la rama normal.',
            );
        }
    }

    public function testLosSeisMetodosDeCancelacionSonAceptadosPorElServicio(): void
    {
        $service = $this->cancelService();

        $receipt = $service->cancel([
            new CancellationUuid(self::UUID, CancellationReason::OperationNotCarriedOut),
        ]);

        self::assertFalse($receipt->isSuccess());
        self::assertTrue($receipt->isCredentialError(), sprintf(
            'cancel: se esperaba «Invalid Username or Password». CodEstatus recibido: %s',
            var_export($receipt->getStatusCode(), true),
        ));
        self::assertStringContainsStringIgnoringCase('invalid', (string) $receipt->getErrorMessage());

        $acceptReject = $service->acceptReject([self::UUID => 'Aceptacion'], self::RECEIVER_ID);

        self::assertFalse($acceptReject->isSuccess());
        self::assertTrue($acceptReject->isCredentialError(), 'accept_reject reporta el fallo en <error>.');

        $satStatus = $service->getSatStatus(
            uuid: self::UUID,
            taxpayerId: self::TAXPAYER_ID,
            receiverTaxpayerId: self::RECEIVER_ID,
            total: '1160.00',
        );

        self::assertFalse($satStatus->isSuccess());
        self::assertNull($satStatus->details, 'Con credenciales inválidas no hay detalle del SAT.');
        self::assertTrue($satStatus->isCredentialError());

        $pending = $service->getPending(self::RECEIVER_ID);

        self::assertFalse($pending->isSuccess());
        self::assertTrue($pending->isEmpty());
        self::assertTrue($pending->isCredentialError());

        $acknowledgment = $service->getReceipt(self::UUID, ReceiptType::Reception);

        self::assertFalse($acknowledgment->isSuccess());
        self::assertFalse($acknowledgment->hasReceipt());
        self::assertTrue($acknowledgment->isCredentialError());

        $pendingCancellation = $service->queryPendingCancellation(self::UUID);

        self::assertFalse($pendingCancellation->isSuccess());
        self::assertTrue($pendingCancellation->isCredentialError());
        self::assertFalse($pendingCancellation->isGone(), 'Un fallo de credenciales no significa que el UUID salió de la cola.');
        self::assertStringContainsStringIgnoringCase('invalid', (string) $pendingCancellation->getErrorMessage());
    }

    public function testElEndpointDeProduccionNoAceptaCredencialesDeDemo(): void
    {
        // El error 300 documentado también ocurre al usar la URL de un ambiente
        // con las credenciales del otro; aquí basta comprobar que el host de
        // producción responde al envelope.
        $provider = new CredentialsProvider([
            'produccion' => new Credentials(
                name: 'produccion',
                username: self::USER,
                password: self::PASSWORD,
                taxpayerId: self::TAXPAYER_ID,
                environment: Environment::Production,
            ),
        ]);

        $service = new StampService(
            $this->transport(),
            new EndpointResolver(),
            $provider,
            new CfdiPreflightValidator(),
        );

        $receipt = $service->stamp(Fixtures::signedCfdi());

        self::assertFalse($receipt->isSuccess());
        self::assertTrue($receipt->hasErrorCode(ErrorCode::InvalidCredentials) || $receipt->hasFault());
    }
}
