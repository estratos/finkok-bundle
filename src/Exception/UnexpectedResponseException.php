<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Exception;

/**
 * La respuesta recibida no tiene la forma esperada: no es XML, no contiene
 * `Envelope/Body`, falta el elemento de resultado de la operación, o su
 * contenido no puede interpretarse.
 *
 * Normalmente indica que se está apuntando a una URL equivocada (por ejemplo el
 * WSDL de retenciones contra el servicio de CFDI) o que un proxy intermedio
 * modificó la respuesta.
 */
final class UnexpectedResponseException extends FinkokException
{
    public function __construct(
        string $message,
        private readonly ?string $endpoint = null,
        private readonly ?string $operation = null,
        private readonly ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function getOperation(): ?string
    {
        return $this->operation;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
