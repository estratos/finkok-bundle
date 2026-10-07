<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

/**
 * Entrada individual de aceptación o rechazo de cancelación.
 *
 * Corresponde a los complexType `apps.services.soap.core.views:Acepta` y
 * `apps.services.soap.core.views:Rechaza`, que tienen la misma forma.
 *
 * `status` NO usa el rango 201-212 del método `cancel`: el método
 * `accept_reject` responde con los códigos 1000-1006 tipificados en
 * {@see AcceptRejectStatus}.
 */
final class AcceptRejectEntry
{
    public function __construct(
        public readonly ?string $uuid = null,
        /** `status` devuelto por el SAT para este UUID (rango 1000-1006). */
        public readonly ?string $status = null,
        /** `true` si la entrada proviene del arreglo de aceptación. */
        public readonly bool $accepted = false,
    ) {
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function isRejected(): bool
    {
        return !$this->accepted;
    }

    /**
     * Código tipificado, o `null` si Finkok devolvió un valor no documentado.
     */
    public function statusCode(): ?AcceptRejectStatus
    {
        if (null === $this->status) {
            return null;
        }

        return AcceptRejectStatus::tryFrom(trim($this->status));
    }

    /**
     * `true` cuando el SAT registró la respuesta (código 1000).
     */
    public function isAcknowledged(): bool
    {
        return true === $this->statusCode()?->isSuccess();
    }

    /**
     * `true` cuando no había nada que responder (1001 o 1002).
     */
    public function isNothingToAnswer(): bool
    {
        return true === $this->statusCode()?->isNothingToAnswer();
    }

    public function getStatusDescription(): ?string
    {
        return $this->statusCode()?->description();
    }

    public function getHint(): ?string
    {
        return $this->statusCode()?->hint();
    }

    public function __toString(): string
    {
        return sprintf(
            '%s: %s [%s]',
            $this->uuid ?? 'sin UUID',
            $this->accepted ? 'aceptado' : 'rechazado',
            $this->status ?? 'sin status',
        );
    }
}
