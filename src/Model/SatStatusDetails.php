<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

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
        /**
         * `ValidacionEFOS`: validación del RFC emisor contra el listado de EFOS del SAT (art. 69-B del
         * CFF). Códigos del SAT: `100`, `101` y `104` = el emisor está listado; `102` y `103` = el emisor
         * no está listado pero sí un RFC de terceros («a cuenta de terceros»); `200` y `201` = no
         * listado. Ver {@see isListedAsEfos()} y {@see hasListedThirdParty()}.
         */
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
     * `true` cuando el **emisor** aparece en el listado de EFOS (empresas que facturan operaciones
     * simuladas, art. 69-B del CFF).
     *
     * ⚠️ Los códigos que listan al emisor son `100`, `101` y `104`. `200`/`201` significan lo
     * contrario —**no** está listado— y `102`/`103` son el caso de un tercero (ver
     * {@see hasListedThirdParty()}). Documentación del SAT del servicio de consulta: en la v1.3,
     * `100` = se encontró en el listado y `200` = no se encontró; la v1.4 agrega `101`/`104` como
     * «listado» y `102`/`103` como «listado a cuenta de terceros».
     */
    public function isListedAsEfos(): bool
    {
        return null !== $this->efosValidation
            && \in_array(trim($this->efosValidation), ['100', '101', '104'], true);
    }

    /**
     * `true` cuando el comprobante se emitió **a cuenta de terceros** y el RFC del tercero aparece en
     * el listado de EFOS (`102` y `103`). En esos códigos el emisor directo **no** está listado; para
     * eso está {@see isListedAsEfos()}.
     */
    public function hasListedThirdParty(): bool
    {
        return null !== $this->efosValidation
            && \in_array(trim($this->efosValidation), ['102', '103'], true);
    }
}
