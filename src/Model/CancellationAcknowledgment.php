<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

use Estratos\FinkokBundle\Model\Concerns\AssertsSuccess;

/**
 * Resultado de `get_receipt`.
 *
 * Devuelve el acuse (XML) que el SAT emitió para una cancelación, distinguiendo
 * entre el acuse del emisor y el del receptor mediante el parámetro `type`.
 *
 * Corresponde al complexType `apps.services.soap.core.views:ReceiptResult`.
 */
final class CancellationAcknowledgment implements FinkokResultInterface
{
    use AssertsSuccess;

    public function __construct(
        public readonly ?string $uuid = null,
        /** `success` reportado por Finkok. */
        public readonly ?bool $success = null,
        /** `receipt`: acuse del SAT. */
        public readonly ?string $receipt = null,
        /** `taxpayer_id` del emisor consultado. */
        public readonly ?string $taxpayerId = null,
        public readonly ?string $error = null,
        public readonly ?string $date = null,
        public readonly IncidenceCollection $incidences = new IncidenceCollection(),
        public readonly ?string $rawResponse = null,
    ) {
    }

    /**
     * `true` cuando Finkok devolvió el acuse sin error.
     */
    public function isSuccess(): bool
    {
        if (null !== $this->error && '' !== trim($this->error)) {
            return false;
        }

        if (false === $this->success) {
            return false;
        }

        return true === $this->success || $this->hasReceipt();
    }

    public function hasError(): bool
    {
        return null !== $this->error && '' !== trim($this->error);
    }

    public function hasReceipt(): bool
    {
        return null !== $this->receipt && '' !== trim($this->receipt);
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
