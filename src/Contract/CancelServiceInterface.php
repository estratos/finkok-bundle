<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Contract;

use Finkok\CfdiBundle\Config\CredentialsInterface;
use Finkok\CfdiBundle\Model\AcceptRejectAnswer;
use Finkok\CfdiBundle\Model\AcceptRejectResult;
use Finkok\CfdiBundle\Model\CancellationAcknowledgment;
use Finkok\CfdiBundle\Model\CancellationReceipt;
use Finkok\CfdiBundle\Model\CancellationUuid;
use Finkok\CfdiBundle\Model\PendingCancellations;
use Finkok\CfdiBundle\Model\QueryPendingResult;
use Finkok\CfdiBundle\Model\ReceiptType;
use Finkok\CfdiBundle\Model\SatStatusResult;
use Finkok\CfdiBundle\Xml\CfdiDocument;

/**
 * Web Service de cancelación de Finkok (`cancel.wsdl`).
 *
 * Métodos disponibles: `cancel`, `accept_reject`, `get_sat_status`, `get_pending`,
 * `get_receipt` y `query_pending_cancellation`.
 *
 * Advertencias de integración documentadas por Finkok:
 *  - el código 201 confirma que la **petición** se realizó, no que el CFDI esté
 *    cancelado: hay que confirmarlo con {@see self::getSatStatus()};
 *  - el parámetro `total` de `get_sat_status` debe llevar los decimales exactos
 *    del comprobante o el SAT responde «N 601»;
 *  - cada UUID admite un máximo de 5 intentos de cancelación (después, 799).
 */
interface CancelServiceInterface
{
    /**
     * Cancela uno o varios CFDI (método `Cancel`).
     *
     * @param iterable<string|CancellationUuid> $uuids        UUIDs a cancelar, con
     *                                                        motivo y folio de sustitución cuando aplique
     * @param string|null                       $taxpayerId   RFC del emisor; `null` usa el del perfil
     * @param bool                              $storePending `false` evita la incidencia
     *                                                        «Already en BufferCancellation»
     * @param CredentialsInterface|null         $credentials  perfil a usar; `null` usa el por defecto
     */
    public function cancel(
        iterable $uuids,
        ?string $taxpayerId = null,
        bool $storePending = true,
        ?CredentialsInterface $credentials = null,
    ): CancellationReceipt;

    /**
     * Acepta o rechaza solicitudes de cancelación como receptor (método `Accept_reject`).
     *
     * @param array<string, AcceptRejectAnswer|string> $answers UUID => respuesta
     * @param string|null                              $receiverTaxpayerId RFC del receptor que responde
     */
    public function acceptReject(
        array $answers,
        ?string $receiverTaxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): AcceptRejectResult;

    /**
     * Consulta ante el SAT el estado de un CFDI (método `Get_sat_status`).
     *
     * @param string      $uuid              UUID del CFDI
     * @param string|null $taxpayerId        RFC del emisor
     * @param string|null $receiverTaxpayerId RFC del receptor
     * @param string|null $total             total del comprobante con sus decimales exactos
     */
    public function getSatStatus(
        string $uuid,
        ?string $taxpayerId = null,
        ?string $receiverTaxpayerId = null,
        ?string $total = null,
        ?CredentialsInterface $credentials = null,
    ): SatStatusResult;

    /**
     * Consulta el estado de un CFDI tomando UUID, RFC de emisor y receptor y
     * total directamente del XML.
     *
     * Evita el error «N 601 La expresión impresa proporcionada no es válida», que
     * casi siempre se debe a un `total` mal formado.
     */
    public function getStatusOf(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): SatStatusResult;

    /**
     * Lista los UUID con cancelación pendiente de un receptor (método `Get_pending`).
     *
     * @param string|null $receiverTaxpayerId RFC del receptor; `null` usa el del perfil
     */
    public function getPending(
        ?string $receiverTaxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): PendingCancellations;

    /**
     * Recupera el acuse de recepción o de cancelación de un UUID (método `Get_receipt`).
     *
     * @param string|null $taxpayerId RFC del emisor; `null` usa el del perfil
     */
    public function getReceipt(
        string $uuid,
        ReceiptType $type = ReceiptType::Reception,
        ?string $taxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): CancellationAcknowledgment;

    /**
     * Consulta el estado de una cancelación que quedó en el buffer de Finkok
     * (método `Query_pending_cancellation`).
     */
    public function queryPendingCancellation(
        string $uuid,
        ?CredentialsInterface $credentials = null,
    ): QueryPendingResult;
}
