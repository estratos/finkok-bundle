<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

/**
 * Resultado individual de cancelación por UUID.
 *
 * Corresponde al complexType `apps.services.soap.core.views:Folio` que viaja
 * dentro de `CancelaCFDResult`.
 *
 * `EstatusUUID` es el código con el que el SAT responde la solicitud de
 * cancelación:
 *
 * | Código | Significado                                                              |
 * |--------|--------------------------------------------------------------------------|
 * | 201    | Cancelación exitosa, comprobante sin aceptación del receptor              |
 * | 202    | Cancelación exitosa, comprobante con aceptación del receptor              |
 * | 203    | El CFDI no es cancelable                                                  |
 * | 204    | Solicitud de cancelación enviada correctamente (en proceso, con aceptación)|
 * | 205    | Solicitud de cancelación enviada correctamente (en proceso, sin aceptación)|
 */
final class CancellationFolio
{
    public const STATUS_CANCELLED = '201';
    public const STATUS_CANCELLED_WITH_ACCEPTANCE = '202';
    public const STATUS_NOT_CANCELLABLE = '203';
    public const STATUS_IN_PROCESS = '204';
    public const STATUS_IN_PROCESS_NO_ACCEPTANCE = '205';

    public function __construct(
        public readonly ?string $uuid = null,
        /** `EstatusUUID`: código de respuesta del SAT. */
        public readonly ?string $statusUuid = null,
        /** `EstatusCancelacion`: descripción textual del estatus. */
        public readonly ?string $cancellationStatus = null,
    ) {
    }

    /**
     * `true` cuando el SAT aceptó la cancelación (códigos 201 y 202).
     */
    public function isCancelled(): bool
    {
        return \in_array($this->normalizedStatus(), [self::STATUS_CANCELLED, self::STATUS_CANCELLED_WITH_ACCEPTANCE], true);
    }

    /**
     * `true` cuando la solicitud fue aceptada pero se resolverá después
     * (códigos 204 y 205), normalmente porque falta la aceptación del receptor.
     */
    public function isInProcess(): bool
    {
        return \in_array($this->normalizedStatus(), [self::STATUS_IN_PROCESS, self::STATUS_IN_PROCESS_NO_ACCEPTANCE], true);
    }

    /**
     * `true` cuando el CFDI no admite cancelación (código 203).
     */
    public function isNotCancellable(): bool
    {
        return self::STATUS_NOT_CANCELLABLE === $this->normalizedStatus();
    }

    /**
     * `true` cuando el comprobante contiene aceptación del receptor, requisito
     * para que la cancelación pueda quedar en proceso (códigos 202 y 204).
     */
    public function requiresReceiverAcceptance(): bool
    {
        return \in_array($this->normalizedStatus(), [self::STATUS_CANCELLED_WITH_ACCEPTANCE, self::STATUS_IN_PROCESS], true);
    }

    public function getStatusDescription(): ?string
    {
        return match ($this->normalizedStatus()) {
            self::STATUS_CANCELLED => 'Cancelación exitosa: comprobante sin aceptación del receptor.',
            self::STATUS_CANCELLED_WITH_ACCEPTANCE => 'Cancelación exitosa: comprobante con aceptación del receptor.',
            self::STATUS_NOT_CANCELLABLE => 'El CFDI no es cancelable.',
            self::STATUS_IN_PROCESS => 'Solicitud de cancelación enviada correctamente; queda en proceso con aceptación del receptor.',
            self::STATUS_IN_PROCESS_NO_ACCEPTANCE => 'Solicitud de cancelación enviada correctamente; queda en proceso sin aceptación del receptor.',
            default => $this->cancellationStatus,
        };
    }

    /**
     * `true` cuando Finkok devolvió algún estatus reconocido como cancelación.
     */
    public function isCancellationAcknowledged(): bool
    {
        return $this->isCancelled() || $this->isInProcess();
    }

    private function normalizedStatus(): string
    {
        return trim((string) $this->statusUuid);
    }
}
