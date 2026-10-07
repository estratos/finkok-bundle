<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

/**
 * Resultado individual de cancelación por UUID.
 *
 * Corresponde al complexType `apps.services.soap.core.views:Folio` que viaja
 * dentro de `CancelaCFDResult`.
 *
 * Los dos campos que devuelve Finkok responden a preguntas distintas y hay que
 * leerlos juntos:
 *
 *  - `EstatusUUID` es el código de la petición para ese UUID (201-212, ver
 *    {@see CancellationStatusCode});
 *  - `EstatusCancelacion` es el texto del SAT («Cancelado», «En proceso»,
 *    «No cancelable», «Plazo vencido», «Solicitud rechazada»).
 *
 * La advertencia de Finkok es explícita: el código 201 confirma que la
 * **petición** se envió bien, no que el CFDI ya esté cancelado. Por eso
 * {@see self::isCancelled()} exige confirmación textual y
 * {@see self::isRequestAccepted()} responde a la otra pregunta.
 */
final class CancellationFolio
{
    public function __construct(
        public readonly ?string $uuid = null,
        /** `EstatusUUID`: código de la petición para este UUID. */
        public readonly ?string $statusUuid = null,
        /** `EstatusCancelacion`: estatus textual que devuelve el SAT. */
        public readonly ?string $cancellationStatus = null,
    ) {
    }

    /**
     * Código tipificado, o `null` si Finkok devolvió un valor no documentado.
     */
    public function statusCode(): ?CancellationStatusCode
    {
        if (null === $this->statusUuid) {
            return null;
        }

        return CancellationStatusCode::tryFrom(trim($this->statusUuid));
    }

    /**
     * `true` cuando Finkok aceptó la petición para este UUID (201, 202 u 798).
     *
     * No implica que la cancelación sea definitiva: confírmalo con
     * {@see self::isCancelled()} o con `get_sat_status()`.
     */
    public function isRequestAccepted(): bool
    {
        return true === $this->statusCode()?->isRequestAccepted();
    }

    /**
     * `true` cuando la cancelación es definitiva.
     *
     * Se basa en `EstatusCancelacion` («Cancelado», «Cancelado con aceptación»)
     * o en el código 202 («UUID previamente cancelado»). Un 201 sin estatus
     * textual no basta, tal como advierte la documentación de Finkok.
     */
    public function isCancelled(): bool
    {
        if ($this->statusTextSays('cancelado') && !$this->statusTextSays('no cancelable')) {
            return true;
        }

        return CancellationStatusCode::PreviouslyCancelled === $this->statusCode();
    }

    /**
     * `true` cuando la cancelación sigue en proceso, normalmente a la espera de
     * la aceptación del receptor («En proceso»).
     */
    public function isInProcess(): bool
    {
        return $this->statusTextSays('proceso');
    }

    /**
     * `true` cuando el UUID no existe o no corresponde al emisor (203 y 205).
     *
     * En DEMO es habitual el 205 si se cancela en los primeros minutos tras el
     * timbrado: hay que esperar de 2 a 5 minutos.
     */
    public function isNotFound(): bool
    {
        return true === $this->statusCode()?->isNotFound();
    }

    /**
     * `true` cuando el CFDI no admite cancelación.
     */
    public function isNotCancellable(): bool
    {
        return $this->statusTextSays('no cancelable')
            || CancellationStatusCode::NotApplicable === $this->statusCode()
            || CancellationStatusCode::NotCancellable === $this->statusCode();
    }

    /**
     * `true` cuando la petición de este UUID fue rechazada o es inválida.
     */
    public function isRejected(): bool
    {
        if (null !== $this->statusUuid && '' !== trim($this->statusUuid)) {
            return true === $this->statusCode()?->isRejection();
        }

        return $this->statusTextSays('rechazad') || $this->statusTextSays('plazo vencido');
    }

    /**
     * `true` cuando la cancelación requiere la aceptación del receptor.
     */
    public function requiresReceiverAcceptance(): bool
    {
        return $this->statusTextSays('con aceptación');
    }

    /**
     * Descripción del resultado: la del código si está tipificado, o el texto
     * del SAT tal cual.
     */
    public function getStatusDescription(): ?string
    {
        return $this->statusCode()?->description() ?? $this->cancellationStatus;
    }

    public function getHint(): ?string
    {
        return $this->statusCode()?->hint();
    }

    /**
     * `true` cuando la petición fue aceptada, que es la condición que Finkok
     * devuelve para un UUID procesado correctamente.
     */
    public function isCancellationAcknowledged(): bool
    {
        return $this->isRequestAccepted();
    }

    private function statusTextSays(string $needle): bool
    {
        $status = $this->cancellationStatus;

        return null !== $status && str_contains(strtolower(trim($status)), strtolower($needle));
    }
}
