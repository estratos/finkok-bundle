<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Exception;

/**
 * Fallo de red o de HTTP al invocar el Web Service de Finkok: DNS, TLS, timeouts,
 * respuestas 4xx/5xx sin envelope SOAP, cuerpos truncados, etc.
 *
 * Ningún reintento automático debería hacerse sobre esta excepción salvo que el
 * código HTTP sea 429 o 5xx.
 */
final class TransportException extends FinkokException
{
    public function __construct(
        string $message,
        private readonly ?string $endpoint = null,
        private readonly ?string $operation = null,
        private readonly ?int $httpStatusCode = null,
        private readonly ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function forConnectionFailure(
        string $endpoint,
        string $operation,
        \Throwable $previous,
    ): self {
        return new self(
            sprintf('No fue posible conectar con Finkok (%s en %s): %s', $operation, $endpoint, $previous->getMessage()),
            $endpoint,
            $operation,
            null,
            null,
            $previous,
        );
    }

    public static function forHttpError(
        string $endpoint,
        string $operation,
        int $statusCode,
        string $body,
    ): self {
        $body = self::snippet($body);

        return new self(
            sprintf('Finkok respondió HTTP %d al invocar %s en %s. Cuerpo: %s', $statusCode, $operation, $endpoint, $body),
            $endpoint,
            $operation,
            $statusCode,
            $body,
        );
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function getOperation(): ?string
    {
        return $this->operation;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    /**
     * Indica si el fallo es potencialmente transitorio y conviene reintentar.
     */
    public function isRetryable(): bool
    {
        return null === $this->httpStatusCode
            || 429 === $this->httpStatusCode
            || $this->httpStatusCode >= 500;
    }

    private static function snippet(string $body, int $limit = 800): string
    {
        $body = trim($body);
        if ('' === $body) {
            return '(vacío)';
        }

        return \strlen($body) > $limit ? substr($body, 0, $limit).'…' : $body;
    }
}
