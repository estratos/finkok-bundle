<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Http;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Construye el cliente HTTP que usa el transporte SOAP.
 *
 * Aplica, por este orden: el cliente base de la aplicación (si existe), los
 * ajustes de `finkok.http` como opciones por defecto y, opcionalmente, la
 * política de reintentos.
 *
 * Sobre los reintentos: Symfony **no** reintenta peticiones POST por defecto,
 * porque un POST no es idempotente. Aquí se habilita explícitamente porque las
 * operaciones de Finkok sí lo son de facto:
 *
 *  - al reintentar un timbrado, el mismo XML produce el mismo UUID y Finkok
 *    responde la incidencia 307 recuperando el comprobante ya timbrado;
 *  - al reintentar una cancelación, Finkok responde 202 («realizada previamente»)
 *    o 798 («ya existe una solicitud previa»).
 *
 * Aun así, la política solo cubre errores de transporte (código 0) y códigos
 * transitorios, nunca respuestas de negocio.
 */
final class HttpClientFactory
{
    /**
     * @param array<string, mixed> $options opciones por defecto del cliente
     * @param array<string, mixed> $retry   parámetros de la política de reintentos
     */
    public function __construct(
        private readonly ?HttpClientInterface $base = null,
        private readonly array $options = [],
        private readonly bool $retryEnabled = true,
        private readonly int $maxRetries = 2,
        private readonly array $retry = [],
    ) {
    }

    public function create(): HttpClientInterface
    {
        $client = $this->base ?? HttpClient::create();

        if ([] !== $this->options) {
            $client = $client->withOptions($this->options);
        }

        if (!$this->retryEnabled || $this->maxRetries < 1) {
            return $client;
        }

        /** @var list<int|string> $statusCodes */
        $statusCodes = $this->retry['http_codes'] ?? [0, 429, 500, 502, 503, 504];

        $strategy = new GenericRetryStrategy(
            statusCodes: array_values(array_map('intval', $statusCodes)),
            delayMs: (int) ($this->retry['delay_ms'] ?? 500),
            multiplier: (float) ($this->retry['multiplier'] ?? 2.0),
            maxDelayMs: (int) ($this->retry['max_delay_ms'] ?? 0),
            jitter: (float) ($this->retry['jitter'] ?? 0.1),
        );

        return new RetryableHttpClient($client, $strategy, $this->maxRetries);
    }

    /**
     * Cliente base de la aplicación, si se detectó el servicio `http_client`.
     */
    public function base(): ?HttpClientInterface
    {
        return $this->base;
    }
}
