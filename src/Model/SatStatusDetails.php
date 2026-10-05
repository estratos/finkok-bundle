<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Model;

/**
 * Detalle del estatus de un CFDI consultado contra el SAT.
 *
 * Corresponde al complexType `apps.services.soap.core.views:AcuseSATConsulta`.
 */
final class SatStatusDetails
{
    public function __construct(
        /** `CodigoEstatus`: "S - Comprobante obtenido satisfactoriamente." o "N - <código> <detalle>". */
        public readonly ?string $statusCode = null,
        /** `EsCancelable`: "Cancelable con aceptación", "Cancelable sin aceptación" o "No cancelable". */
        public readonly ?string $cancellable = null,
        /** `Estado`: "Vigente" o "Cancelado". */
        public readonly ?string $state = null,
        /** `EstatusCancelacion`: "Cancelado", "En proceso", "Solicitud rechazada", … */
        public readonly ?string $cancellationStatus = null,
        /** `ValidacionEFOS`: "100" (no listado), "101", "200", "201". */
        public readonly ?string $efosValidation = null,
        /** `DetallesValidacionEFOS`: mensaje complementario de la validación EFOS. */
        public readonly ?string $efosValidationDetails = null,
    ) {
    }

    /**
     * `true` cuando el SAT localizó el comprobante (`CodigoEstatus` inicia con "S").
     */
    public function isFound(): bool
    {
        return null !== $this->statusCode && str_starts_with(strtoupper(trim($this->statusCode)), 'S');
    }

    /**
     * `true` cuando el comprobante está Vigente ante el SAT.
     */
    public function isActive(): bool
    {
        return null !== $this->state && str_contains(strtolower(trim($this->state)), 'vigente');
    }

    /**
     * `true` cuando el comprobante ya está Cancelado ante el SAT.
     */
    public function isCancelled(): bool
    {
        return null !== $this->state && str_contains(strtolower(trim($this->state)), 'cancelado');
    }

    /**
     * `true` cuando el CFDI admite cancelación.
     */
    public function isCancellable(): bool
    {
        if (null === $this->cancellable) {
            return false;
        }

        $value = strtolower(trim($this->cancellable));

        return str_contains($value, 'cancelable') && !str_contains($value, 'no cancelable');
    }

    /**
     * `true` cuando la cancelación necesita la aceptación del receptor.
     */
    public function requiresReceiverAcceptance(): bool
    {
        return null !== $this->cancellable
            && str_contains(strtolower(trim($this->cancellable)), 'con aceptaci');
    }

    /**
     * `true` cuando la cancelación puede hacerse sin aceptación del receptor.
     */
    public function canCancelWithoutAcceptance(): bool
    {
        return null !== $this->cancellable
            && str_contains(strtolower(trim($this->cancellable)), 'sin aceptaci');
    }

    /**
     * `true` cuando el comprobante aparece listado como EFOS (empresa que
     * factura operaciones simuladas) en los códigos 200/201.
     */
    public function isListedAsEfos(): bool
    {
        return null !== $this->efosValidation && \in_array(trim($this->efosValidation), ['200', '201'], true);
    }
}
