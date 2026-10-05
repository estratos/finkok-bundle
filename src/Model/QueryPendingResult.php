<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Model;

use Finkok\CfdiBundle\Model\Concerns\AssertsSuccess;

/**
 * Resultado del método `query_pending` del Web Service de timbrado.
 *
 * Corresponde al complexType `apps.services.soap.core.views:QueryPendingResult`.
 * Sirve para consultar el estado de un comprobante que quedó en la cola de
 * espera de Finkok antes de ser enviado al SAT.
 */
final class QueryPendingResult implements FinkokResultInterface
{
    use AssertsSuccess;

    /**
     * El comprobante está timbrado pero **aún no** se envió al SAT; sigue en el
     * buffer de Finkok.
     */
    public const STATUS_STAMPED = 'S';

    /**
     * El comprobante fue timbrado y ya se envió al SAT.
     */
    public const STATUS_FINISHED = 'F';

    public function __construct(
        /** `status` del proceso (por ejemplo `pending`, `completed`). */
        public readonly ?string $status = null,
        /** `xml` asociado al UUID consultado, si ya está disponible. */
        public readonly ?string $xml = null,
        public readonly ?string $uuid = null,
        /** `uuid_status`: estado del UUID ante el SAT. */
        public readonly ?string $uuidStatus = null,
        /** `next_attempt`: fecha del siguiente intento de envío al SAT. */
        public readonly ?string $nextAttempt = null,
        /** `attempts`: número de intentos realizados. */
        public readonly ?string $attempts = null,
        public readonly ?string $error = null,
        public readonly ?string $date = null,
        public readonly IncidenceCollection $incidences = new IncidenceCollection(),
    ) {
    }

    /**
     * `true` cuando la consulta se resolvió sin error de servicio.
     *
     * Ojo: un `status` pendiente no es un error, simplemente significa que el
     * comprobante sigue en la cola de Finkok.
     */
    public function isSuccess(): bool
    {
        return !$this->hasError();
    }

    /**
     * `true` cuando Finkok reportó un error, ya sea en `error` (por ejemplo
     * «Invalid Username or Password» en `query_pending_cancellation`) o en el
     * `uuid_status` de un UUID inexistente.
     */
    public function hasError(): bool
    {
        return null !== $this->error && '' !== trim($this->error);
    }

    /**
     * `true` cuando Finkok informa que el comprobante sigue en la cola de espera.
     */
    public function isPending(): bool
    {
        if ($this->isStampedNotSent()) {
            return true;
        }

        return null !== $this->status && str_contains(strtolower(trim($this->status)), 'pending');
    }

    /**
     * `true` cuando el comprobante está timbrado pero todavía no se envió al SAT
     * (`status` = "S"): sigue en el buffer de Finkok y se enviará más tarde.
     */
    public function isStampedNotSent(): bool
    {
        return self::STATUS_STAMPED === trim((string) $this->status);
    }

    /**
     * `true` cuando el comprobante ya se envió al SAT (`status` = "F").
     */
    public function isFinished(): bool
    {
        return self::STATUS_FINISHED === trim((string) $this->status);
    }

    /**
     * `true` cuando el UUID ya fue procesado y enviado al SAT.
     */
    public function isCompleted(): bool
    {
        if ($this->isFinished()) {
            return true;
        }

        return null !== $this->status
            && (str_contains(strtolower(trim($this->status)), 'complete')
                || str_contains(strtolower(trim($this->status)), 'success'));
    }

    /**
     * `true` cuando el UUID consultado ya no está en la cola de pendientes.
     *
     * Se excluyen los fallos de credenciales, que también llegan por `error`
     * pero no significan que el comprobante haya salido de la cola.
     */
    public function isGone(): bool
    {
        return null === $this->status && $this->hasError() && !$this->isCredentialError();
    }

    public function hasXml(): bool
    {
        return null !== $this->xml && '' !== trim($this->xml);
    }

    /**
     * El estado del proceso llega en `status`; si Finkok devolvió un error de
     * servicio (por ejemplo credenciales inválidas) el único texto disponible
     * está en `error`.
     */
    public function getStatusCode(): ?string
    {
        return $this->status ?? $this->error;
    }

    public function getIncidences(): IncidenceCollection
    {
        return $this->incidences;
    }
}
