<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

/**
 * Entrada individual de aceptación o rechazo de cancelación.
 *
 * Corresponde a los complexType `apps.services.soap.core.views:Acepta` y
 * `apps.services.soap.core.views:Rechaza`, que tienen la misma forma.
 */
final class AcceptRejectEntry
{
    public function __construct(
        public readonly ?string $uuid = null,
        /** `status` devuelto por el SAT para este UUID. */
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
     * `true` cuando el SAT confirma la aceptación/rechazo (códigos 2xx).
     */
    public function isAcknowledged(): bool
    {
        return null !== $this->status && str_starts_with(trim($this->status), '2');
    }

    public function __toString(): string
    {
        return sprintf('%s: %s (%s)', $this->uuid ?? 'sin UUID', $this->accepted ? 'aceptado' : 'rechazado', $this->status ?? 'sin status');
    }
}
