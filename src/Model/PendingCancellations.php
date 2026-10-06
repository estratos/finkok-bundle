<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

use Estratos\FinkokBundle\Model\Concerns\AssertsSuccess;

/**
 * Resultado de `get_pending` y `get_out_pending`.
 *
 * Lista los UUID de cancelaciones que están pendientes de respuesta o de
 * aceptación del receptor para un RFC dado.
 *
 * Corresponde al complexType `apps.services.soap.core.views:CancelPendingResult`.
 */
final class PendingCancellations implements FinkokResultInterface
{
    use AssertsSuccess;

    /**
     * @param list<string> $uuids
     */
    public function __construct(
        public readonly array $uuids = [],
        public readonly ?string $error = null,
        public readonly IncidenceCollection $incidences = new IncidenceCollection(),
        public readonly ?string $rawResponse = null,
    ) {
    }

    /**
     * `true` cuando la consulta se resolvió (aunque no haya pendientes).
     */
    public function isSuccess(): bool
    {
        return null === $this->error || '' === trim($this->error);
    }

    public function hasError(): bool
    {
        return null !== $this->error && '' !== trim($this->error);
    }

    public function isEmpty(): bool
    {
        return [] === $this->uuids;
    }

    public function isNotEmpty(): bool
    {
        return [] !== $this->uuids;
    }

    public function count(): int
    {
        return \count($this->uuids);
    }

    /**
     * `true` cuando el UUID indicado está en la lista de pendientes.
     */
    public function contains(string $uuid): bool
    {
        foreach ($this->uuids as $pending) {
            if (0 === strcasecmp(trim($pending), trim($uuid))) {
                return true;
            }
        }

        return false;
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
