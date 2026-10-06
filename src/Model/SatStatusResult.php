<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

use Estratos\FinkokBundle\Model\Concerns\AssertsSuccess;

/**
 * Resultado del método `get_sat_status` del Web Service de cancelación.
 *
 * Corresponde al complexType `apps.services.soap.core.views:AcuseSatEstatus`, que
 * envuelve un `error` y el detalle `sat` (`AcuseSATConsulta`).
 *
 * Es la forma recomendada de conocer, sin cancelar nada, si un CFDI está
 * Vigente o Cancelado y si es cancelable con o sin aceptación del receptor.
 */
final class SatStatusResult implements FinkokResultInterface
{
    use AssertsSuccess;

    public function __construct(
        /** `error` reportado por Finkok, si lo hay. */
        public readonly ?string $error = null,
        /** Nodo `sat` con el detalle de la consulta. */
        public readonly ?SatStatusDetails $details = null,
        public readonly IncidenceCollection $incidences = new IncidenceCollection(),
        public readonly ?string $rawResponse = null,
    ) {
    }

    /**
     * `true` cuando la consulta se resolvió y el SAT localizó el comprobante.
     */
    public function isSuccess(): bool
    {
        if (null !== $this->error && '' !== trim($this->error)) {
            return false;
        }

        return null !== $this->details && $this->details->isFound();
    }

    public function hasError(): bool
    {
        return null !== $this->error && '' !== trim($this->error);
    }

    /**
     * `true` cuando el comprobante está Vigente.
     */
    public function isActive(): bool
    {
        return true === $this->details?->isActive();
    }

    /**
     * `true` cuando el comprobante ya está Cancelado.
     */
    public function isCancelled(): bool
    {
        return true === $this->details?->isCancelled();
    }

    /**
     * `true` cuando el CFDI admite cancelación.
     */
    public function isCancellable(): bool
    {
        return true === $this->details?->isCancellable();
    }

    public function getStatusCode(): ?string
    {
        return $this->details?->statusCode ?? $this->error;
    }

    public function getIncidences(): IncidenceCollection
    {
        return $this->incidences;
    }
}
