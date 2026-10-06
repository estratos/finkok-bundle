<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Soap;

use Estratos\FinkokBundle\Exception\SoapFaultException;
use Estratos\FinkokBundle\Exception\UnexpectedResponseException;
use Estratos\FinkokBundle\Xml\DomReader;

/**
 * Respuesta SOAP de Finkok con acceso tolerante a prefijos.
 *
 * El documento se parsea de forma perezosa: si el cuerpo no es XML, el error se
 * reporta solo cuando alguien intenta leerlo, lo que permite distinguir entre un
 * fallo de transporte (HTTP 502 con HTML) y una respuesta SOAP inválida.
 */
final class SoapResponse
{
    private ?\DOMDocument $document = null;

    private bool $parsed = false;

    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        private readonly string $rawXml,
        private readonly int $httpStatusCode = 200,
        private readonly array $headers = [],
    ) {
    }

    public function raw(): string
    {
        return $this->rawXml;
    }

    public function httpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * @return array<string, list<string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Documento XML parseado.
     *
     * @throws UnexpectedResponseException si el cuerpo no es XML válido
     */
    public function document(): \DOMDocument
    {
        $document = $this->tryDocument();

        if (null === $document) {
            throw new UnexpectedResponseException(
                'La respuesta de Finkok no es un documento XML válido.',
                null,
                null,
                $this->truncated(),
            );
        }

        return $document;
    }

    /**
     * Documento XML parseado, o `null` si el cuerpo no es XML.
     */
    public function tryDocument(): ?\DOMDocument
    {
        if ($this->parsed) {
            return $this->document;
        }

        $this->parsed = true;

        if ('' === trim($this->rawXml)) {
            return $this->document = null;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new \DOMDocument();
            $loaded = @$document->loadXML($this->rawXml, LIBXML_NONET | LIBXML_PARSEHUGE);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $this->document = $loaded ? $document : null;
    }

    public function isXml(): bool
    {
        return null !== $this->tryDocument();
    }

    /**
     * Elemento `soap:Envelope`, si existe.
     */
    public function envelope(): ?\DOMElement
    {
        $root = $this->tryDocument()?->documentElement;

        if (!$root instanceof \DOMElement || 'Envelope' !== $root->localName) {
            return null;
        }

        return $root;
    }

    /**
     * `true` cuando el cuerpo es un envelope SOAP.
     *
     * Se comprueba para no confundir un XML cualquiera (por ejemplo la página de
     * error de un proxy, que puede ser XML válido) con una respuesta del PAC.
     */
    public function isSoapEnvelope(): bool
    {
        return null !== $this->envelope();
    }

    /**
     * Elemento `soap:Body`, o `null` si el envelope no lo tiene.
     */
    public function body(): ?\DOMElement
    {
        $envelope = $this->envelope();

        if (null === $envelope) {
            return null;
        }

        return DomReader::firstElement($envelope, 'Body');
    }

    /**
     * SOAP Fault, o `null` si la respuesta no es un fault.
     */
    public function fault(): ?SoapFault
    {
        $envelope = $this->envelope();

        if (null === $envelope) {
            return null;
        }

        $body = DomReader::firstElement($envelope, 'Body');
        $fault = null === $body ? null : DomReader::firstElement($body, 'Fault');

        // Algunos despliegues colocan el Fault fuera del Body.
        $fault ??= DomReader::descendant($envelope, 'Fault');

        return null === $fault ? null : SoapFault::fromElement($fault);
    }

    public function hasFault(): bool
    {
        return null !== $this->fault();
    }

    /**
     * Lanza la excepción correspondiente si la respuesta es un SOAP Fault.
     *
     * @throws SoapFaultException
     */
    public function assertNoFault(string $endpoint, string $operation): void
    {
        $fault = $this->fault();

        if (null !== $fault) {
            throw SoapFaultException::fromFault($endpoint, $operation, $fault->code, $fault->message);
        }
    }

    /**
     * Elemento de resultado de la operación (por ejemplo `stampResult`).
     *
     * Resuelve las formas que usa Finkok: `<operacion>Response` con el resultado
     * como primer hijo, cualquier elemento terminado en `Response`, o el propio
     * resultado directamente dentro del Body. Devuelve `null` cuando el Body
     * está vacío.
     */
    public function result(?string $operation = null): ?\DOMElement
    {
        $body = $this->body();

        if (null === $body) {
            return null;
        }

        if (null !== $operation) {
            $wrapper = DomReader::firstElement($body, $operation.'Response');
            if (null !== $wrapper) {
                return self::firstChildElement($wrapper) ?? $wrapper;
            }
        }

        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMElement && str_ends_with($child->localName, 'Response')) {
                return self::firstChildElement($child);
            }
        }

        return self::firstChildElement($body);
    }

    /**
     * Texto del nodo de resultado con ese nombre local, o `null`.
     */
    public function resultText(string $localName, ?string $operation = null): ?string
    {
        $result = $this->result($operation);

        return null === $result ? null : DomReader::textOf($result, $localName);
    }

    public function describe(): string
    {
        $fault = $this->fault();

        return sprintf(
            'HTTP %d | %s | %s',
            $this->httpStatusCode,
            $this->isXml() ? 'XML' : 'no-XML',
            $fault?->describe() ?? 'sin fault',
        );
    }

    private static function firstChildElement(\DOMNode $node): ?\DOMElement
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return $child;
            }
        }

        return null;
    }

    private function truncated(int $limit = 1500): string
    {
        return \strlen($this->rawXml) > $limit ? substr($this->rawXml, 0, $limit).'…' : $this->rawXml;
    }
}
