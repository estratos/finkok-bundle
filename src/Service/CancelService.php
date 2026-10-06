<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Service;

use Estratos\FinkokBundle\Config\CredentialsInterface;
use Estratos\FinkokBundle\Config\CredentialsProviderInterface;
use Estratos\FinkokBundle\Config\EndpointResolver;
use Estratos\FinkokBundle\Config\Service;
use Estratos\FinkokBundle\Contract\CancelServiceInterface;
use Estratos\FinkokBundle\Exception\ValidationException;
use Estratos\FinkokBundle\Hydrator\AcceptRejectResultHydrator;
use Estratos\FinkokBundle\Hydrator\CancellationAcknowledgmentHydrator;
use Estratos\FinkokBundle\Hydrator\CancellationReceiptHydrator;
use Estratos\FinkokBundle\Hydrator\PendingCancellationsHydrator;
use Estratos\FinkokBundle\Hydrator\QueryPendingResultHydrator;
use Estratos\FinkokBundle\Hydrator\SatStatusResultHydrator;
use Estratos\FinkokBundle\Model\AcceptRejectAnswer;
use Estratos\FinkokBundle\Model\AcceptRejectResult;
use Estratos\FinkokBundle\Model\CancellationAcknowledgment;
use Estratos\FinkokBundle\Model\CancellationReceipt;
use Estratos\FinkokBundle\Model\CancellationUuid;
use Estratos\FinkokBundle\Model\PendingCancellations;
use Estratos\FinkokBundle\Model\QueryPendingResult;
use Estratos\FinkokBundle\Model\ReceiptType;
use Estratos\FinkokBundle\Model\SatStatusResult;
use Estratos\FinkokBundle\Soap\SoapNamespaces;
use Estratos\FinkokBundle\Soap\SoapTransportInterface;
use Estratos\FinkokBundle\Soap\Value\AttributeElement;
use Estratos\FinkokBundle\Soap\Value\Base64EncodedValue;
use Estratos\FinkokBundle\Soap\Value\ComplexValue;
use Estratos\FinkokBundle\Soap\Value\RepeatedValue;
use Estratos\FinkokBundle\Xml\CfdiDocument;
use Psr\Log\LoggerInterface;

/**
 * Implementación del Web Service de cancelación de Finkok sobre SOAP document/literal.
 *
 * Notas de construcción del envelope: el WSDL de cancelación declara los tipos
 * de datos compartidos (`UUIDArray`, `UUID`, `UUIDS_AR`, `UUID_AR`…) en el
 * namespace `apps.services.soap.core.views`, distinto del namespace del servicio.
 * Cada elemento se emite en el namespace en el que fue declarado, tal como lo
 * haría un cliente generado a partir del WSDL.
 */
final class CancelService extends AbstractFinkokService implements CancelServiceInterface
{
    public function __construct(
        SoapTransportInterface $transport,
        EndpointResolver $endpointResolver,
        CredentialsProviderInterface $credentialsProvider,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($transport, $endpointResolver, $credentialsProvider, $logger);
    }

    public function cancel(
        iterable $uuids,
        ?string $taxpayerId = null,
        bool $storePending = true,
        ?CredentialsInterface $credentials = null,
    ): CancellationReceipt {
        $credentials ??= $this->resolveCredentials(null);
        $list = CancellationUuid::normalizeList($uuids);

        if ([] === $list) {
            throw ValidationException::forEmptyArgument('uuids');
        }

        $taxpayerId = self::normalizeRfc($taxpayerId) ?? $credentials->requireTaxpayerId();

        // Orden según el WSDL de `cancel`:
        // UUIDS, username, password, taxpayer_id, cer, key, store_pending.
        $arguments = [
            'UUIDS' => new RepeatedValue(
                'UUID',
                array_map(
                    static fn (CancellationUuid $uuid): AttributeElement => new AttributeElement([
                        'UUID' => $uuid->uuid,
                        'Motivo' => $uuid->reason?->value,
                        'FolioSustitucion' => $uuid->replacementFolio,
                    ]),
                    $list,
                ),
                SoapNamespaces::VIEWS,
            ),
            ...$this->authenticationArguments($credentials),
            'taxpayer_id' => $taxpayerId,
            'cer' => $this->csdArgument($credentials->certificateBase64()),
            'key' => $this->csdArgument($credentials->privateKeyBase64()),
            'store_pending' => $storePending,
        ];

        $response = $this->call('cancel', $arguments, $credentials);
        $receipt = CancellationReceiptHydrator::hydrate($response->result('cancel'), $response->raw());

        $this->logger->info('Finkok: resultado de la cancelación', [
            'perfil' => $credentials->name(),
            'taxpayer_id' => $taxpayerId,
            'uuids' => array_map(static fn (CancellationUuid $u): string => $u->uuid, $list),
            'cod_estatus' => $receipt->status,
            'exito' => $receipt->isSuccess(),
        ]);

        return $receipt;
    }

    public function acceptReject(
        array $answers,
        ?string $receiverTaxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): AcceptRejectResult {
        $credentials ??= $this->resolveCredentials(null);

        if ([] === $answers) {
            throw ValidationException::forEmptyArgument('answers');
        }

        $items = [];
        foreach ($answers as $uuid => $answer) {
            $value = $answer instanceof AcceptRejectAnswer ? $answer->value : (string) $answer;

            $items[] = new ComplexValue([
                'uuid' => (string) $uuid,
                'respuesta' => $value,
            ]);
        }

        $receiverTaxpayerId = self::normalizeRfc($receiverTaxpayerId) ?? $credentials->taxpayerId();

        // Orden según el WSDL: UUIDS_AR, username, password, rtaxpayer_id, cer, key.
        $arguments = [
            'UUIDS_AR' => new ComplexValue(
                ['uuids_ar' => new RepeatedValue('UUID_AR', $items)],
                SoapNamespaces::VIEWS,
            ),
            ...$this->authenticationArguments($credentials),
            'rtaxpayer_id' => $receiverTaxpayerId,
            'cer' => $this->csdArgument($credentials->certificateBase64()),
            'key' => $this->csdArgument($credentials->privateKeyBase64()),
        ];

        $response = $this->call('accept_reject', $arguments, $credentials);

        return AcceptRejectResultHydrator::hydrate($response->result('accept_reject'), $response->raw());
    }

    public function getSatStatus(
        string $uuid,
        ?string $taxpayerId = null,
        ?string $receiverTaxpayerId = null,
        ?string $total = null,
        ?CredentialsInterface $credentials = null,
    ): SatStatusResult {
        $credentials ??= $this->resolveCredentials(null);

        // Orden según el WSDL: username, password, taxpayer_id, rtaxpayer_id, uuid, total.
        $arguments = [
            ...$this->authenticationArguments($credentials),
            'taxpayer_id' => self::normalizeRfc($taxpayerId) ?? $credentials->taxpayerId(),
            'rtaxpayer_id' => self::normalizeRfc($receiverTaxpayerId),
            'uuid' => trim($uuid),
            'total' => null === $total ? null : trim($total),
        ];

        $response = $this->call('get_sat_status', $arguments, $credentials);
        $status = SatStatusResultHydrator::hydrate($response->result('get_sat_status'), $response->raw());

        $this->logger->info('Finkok: estatus del CFDI ante el SAT', [
            'uuid' => $uuid,
            'exito' => $status->isSuccess(),
            'estado' => $status->details?->state,
            'codigo_estatus' => $status->details?->statusCode,
            'es_cancelable' => $status->details?->cancellable,
        ]);

        return $status;
    }

    public function getStatusOf(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): SatStatusResult
    {
        $document = CfdiDocument::coerce($cfdi);
        $credentials ??= $this->resolveCredentials(null);

        $uuid = $document->uuid();

        if (null === $uuid) {
            throw new ValidationException(
                'El CFDI no contiene un TimbreFiscalDigital, por lo que no es posible obtener su UUID. '
                .'Para consultar el estatus de un comprobante sin XML usa getSatStatus() con los datos explícitos.',
            );
        }

        return $this->getSatStatus(
            uuid: $uuid,
            taxpayerId: $document->emitterRfc(),
            receiverTaxpayerId: $document->receiverRfc(),
            total: $document->total(),
            credentials: $credentials,
        );
    }

    public function getPending(
        ?string $receiverTaxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): PendingCancellations {
        $credentials ??= $this->resolveCredentials(null);

        // Orden según el WSDL: username, password, rtaxpayer_id.
        $arguments = [
            ...$this->authenticationArguments($credentials),
            'rtaxpayer_id' => self::normalizeRfc($receiverTaxpayerId) ?? $credentials->taxpayerId(),
        ];

        $response = $this->call('get_pending', $arguments, $credentials);

        return PendingCancellationsHydrator::hydrate($response->result('get_pending'), $response->raw());
    }

    public function getReceipt(
        string $uuid,
        ReceiptType $type = ReceiptType::Reception,
        ?string $taxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): CancellationAcknowledgment {
        $credentials ??= $this->resolveCredentials(null);

        // Orden según el WSDL: username, password, taxpayer_id, uuid, type.
        $arguments = [
            ...$this->authenticationArguments($credentials),
            'taxpayer_id' => self::normalizeRfc($taxpayerId) ?? $credentials->requireTaxpayerId(),
            'uuid' => trim($uuid),
            'type' => $type->value,
        ];

        $response = $this->call('get_receipt', $arguments, $credentials);

        return CancellationAcknowledgmentHydrator::hydrate($response->result('get_receipt'), $response->raw());
    }

    public function queryPendingCancellation(
        string $uuid,
        ?CredentialsInterface $credentials = null,
    ): QueryPendingResult {
        $credentials ??= $this->resolveCredentials(null);

        // Orden según el WSDL: username, password, uuid.
        $arguments = [
            ...$this->authenticationArguments($credentials),
            'uuid' => trim($uuid),
        ];

        $response = $this->call('query_pending_cancellation', $arguments, $credentials);

        return QueryPendingResultHydrator::hydrate($response->result('query_pending_cancellation'));
    }

    /**
     * El campo `cer`/`key` del WSDL es `xs:base64Binary`, que en SOAP se
     * serializa como texto base64. Como el codificador CSD ya entrega base64, se
     * envía tal cual para no codificarlo dos veces (incidencia 704).
     */
    private function csdArgument(?string $encoded): ?Base64EncodedValue
    {
        if (null === $encoded || '' === trim($encoded)) {
            return null;
        }

        return Base64EncodedValue::fromEncoded($encoded);
    }

    private static function normalizeRfc(?string $rfc): ?string
    {
        if (null === $rfc) {
            return null;
        }

        $rfc = strtoupper(trim($rfc));

        return '' === $rfc ? null : $rfc;
    }

    protected function service(): Service
    {
        return Service::Cancel;
    }
}
