<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Model;

use Finkok\CfdiBundle\Model\Concerns\AssertsSuccess;

/**
 * Resultado del método `accept_reject` del Web Service de cancelación.
 *
 * Se usa cuando el receptor de un CFDI debe aceptar o rechazar la solicitud de
 * cancelación que el emisor envió previamente.
 *
 * Corresponde al complexType `apps.services.soap.core.views:AcceptRejectResult`.
 */
final class AcceptRejectResult implements FinkokResultInterface
{
    use AssertsSuccess;

    /**
     * @param list<AcceptRejectEntry> $accepted
     * @param list<AcceptRejectEntry> $rejected
     */
    public function __construct(
        public readonly array $accepted = [],
        public readonly array $rejected = [],
        public readonly ?string $error = null,
        public readonly IncidenceCollection $incidences = new IncidenceCollection(),
        public readonly ?string $rawResponse = null,
    ) {
    }

    /**
     * `true` cuando el SAT recibió las respuestas de aceptación/rechazo.
     */
    public function isSuccess(): bool
    {
        return null === $this->error || '' === trim($this->error);
    }

    public function hasError(): bool
    {
        return null !== $this->error && '' !== trim($this->error);
    }

    public function countAccepted(): int
    {
        return \count($this->accepted);
    }

    public function countRejected(): int
    {
        return \count($this->rejected);
    }

    /**
     * @return list<AcceptRejectEntry>
     */
    public function all(): array
    {
        return array_merge($this->accepted, $this->rejected);
    }

    public function getStatusCode(): ?string
    {
        return $this->error;
    }

    public function getIncidences(): IncidenceCollection
    {
        return $this->incidences;
    }
}
