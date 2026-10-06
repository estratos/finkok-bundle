<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Contract;

use Estratos\FinkokBundle\Config\CredentialsInterface;
use Estratos\FinkokBundle\Model\QueryPendingResult;
use Estratos\FinkokBundle\Model\StampReceipt;
use Estratos\FinkokBundle\Xml\CfdiDocument;

/**
 * Web Service de timbrado de Finkok (`stamp.wsdl`).
 *
 * Métodos disponibles: `stamp`, `quick_stamp`, `stamped`, `query_pending` y
 * `sign_stamp`.
 *
 * Todos los métodos devuelven DTOs. Las incidencias de negocio (300, 301, 705,
 * 307, CFDI40102…) **no** lanzan excepción: se leen en el DTO con
 * `isSuccess()`, `getIncidences()` o `hasErrorCode()`, y se pueden convertir en
 * excepción con `assertSuccess()`.
 */
interface StampServiceInterface
{
    /**
     * Timbra un CFDI (método `Stamp`).
     *
     * Finkok valida y sella el comprobante y lo guarda en una cola de espera para
     * enviarlo al SAT en el mejor momento. Si el CFDI ya estaba timbrado,
     * responde con `CodEstatus` = «Comprobante timbrado previamente» y la
     * incidencia 307, recuperando el XML original.
     *
     * @param CfdiDocument|string $cfdi        XML del CFDI, ruta de archivo o `CfdiDocument`
     * @param CredentialsInterface|null $credentials perfil a usar; `null` usa el perfil por defecto
     */
    public function stamp(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): StampReceipt;

    /**
     * Timbra un CFDI con el servicio rápido (método `Quick_stamp`).
     *
     * Recomendado para volúmenes altos. A diferencia de `stamp()`, si el CFDI ya
     * estaba timbrado devuelve un error en lugar de recuperar el XML.
     */
    public function quickStamp(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): StampReceipt;

    /**
     * Recupera la información de un CFDI timbrado previamente (método `Stamped`).
     *
     * Se envía el XML **original** (sin timbre) y Finkok devuelve el XML
     * timbrado. Si el comprobante no está timbrado responde la incidencia 603.
     */
    public function stamped(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): StampReceipt;

    /**
     * Timbra usando los CSD que estén cargados en el panel de Finkok para el RFC
     * emisor (método `Sign_stamp`).
     *
     * Requiere que el certificado esté registrado en el panel; en caso contrario
     * Finkok responde 719/720.
     */
    public function signStamp(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): StampReceipt;

    /**
     * Consulta el estado de un comprobante que quedó en la cola de Finkok
     * (método `Query_pending`).
     */
    public function queryPending(string $uuid, ?CredentialsInterface $credentials = null): QueryPendingResult;

    /**
     * Construye el envelope que enviaría `stamp()` sin abrir conexión.
     *
     * Útil para depurar incidencias 705/738, para revisar qué se está enviando
     * exactamente o para pruebas de contrato.
     */
    public function previewStampRequest(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): string;
}
