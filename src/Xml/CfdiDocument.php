<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Xml;

use Estratos\FinkokBundle\Exception\ValidationException;

/**
 * Documento CFDI listo para enviarse a Finkok.
 *
 * Encapsula el XML y expone —sin obligar a parsearlo otra vez en la aplicación—
 * los datos que Finkok pide o que se necesitan para decidir el flujo:
 * UUID del timbre previo, RFC emisor, total y versión.
 *
 * ```php
 * $cfdi = CfdiDocument::fromFile('/ruta/factura.xml');
 *
 * if ($cfdi->hasStamp()) {
 *     // Ya trae TimbreFiscalDigital: stamp() devolverá la incidencia 307,
 *     // pero Finkok recupera el XML original. Alternativamente usa stamped().
 * }
 *
 * $receipt = $stampService->stamp($cfdi);
 * ```
 */
final class CfdiDocument
{
    /**
     * Límite de 1 MB por archivo XML documentado por Finkok. Superarlo puede
     * afectar el timbrado de comprobantes posteriores.
     */
    public const MAX_SIZE_BYTES = 1048576;

    private const TFD_NAMESPACE = 'http://www.sat.gob.mx/TimbreFiscalDigital';

    private const CFD_NAMESPACES = [
        'http://www.sat.gob.mx/cfd/3',
        'http://www.sat.gob.mx/cfd/4',
    ];

    private ?\DOMDocument $document = null;

    private bool $parsed = false;

    private function __construct(
        private readonly string $xml,
        private readonly ?string $source = null,
    ) {
    }

    /**
     * Crea el documento a partir de una cadena XML.
     */
    public static function fromString(string $xml): self
    {
        return new self($xml);
    }

    /**
     * Crea el documento leyendo un archivo local.
     *
     * @throws ValidationException si el archivo no existe o no puede leerse
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw ValidationException::forUnreadableFile($path, 'del CFDI');
        }

        $contents = @file_get_contents($path);

        if (false === $contents) {
            throw ValidationException::forUnreadableFile($path, 'del CFDI');
        }

        return new self($contents, $path);
    }

    /**
     * Acepta indistintamente una cadena XML, una ruta de archivo o un documento
     * ya construido, para simplificar las firmas de los servicios.
     */
    public static function coerce(self|string $xml): self
    {
        if ($xml instanceof self) {
            return $xml;
        }

        // Un CFDI siempre empieza por "<" (o por la declaración XML), mientras
        // que una ruta nunca contiene "<".
        if (!str_contains($xml, '<') && (is_file($xml) || str_ends_with(strtolower($xml), '.xml'))) {
            return self::fromFile($xml);
        }

        return new self($xml);
    }

    /**
     * XML crudo, tal como se enviará a Finkok (antes de codificar en base64).
     */
    public function content(): string
    {
        return $this->xml;
    }

    /**
     * Ruta del archivo de origen, si se creó con `fromFile()`.
     */
    public function source(): ?string
    {
        return $this->source;
    }

    public function sizeInBytes(): int
    {
        return \strlen($this->xml);
    }

    /**
     * `true` cuando el XML supera el límite de 1 MB que impone Finkok.
     */
    public function exceedsSizeLimit(): bool
    {
        return $this->sizeInBytes() > self::MAX_SIZE_BYTES;
    }

    /**
     * `true` cuando el XML es un documento bien formado.
     */
    public function isWellFormed(): bool
    {
        return null !== $this->tryDocument();
    }

    /**
     * Documento DOM parseado.
     *
     * @throws ValidationException si el XML no está bien formado
     */
    public function document(): \DOMDocument
    {
        $document = $this->tryDocument();

        if (null === $document) {
            throw ValidationException::forMalformedXml('el documento no puede parsearse como XML');
        }

        return $document;
    }

    public function tryDocument(): ?\DOMDocument
    {
        if ($this->parsed) {
            return $this->document;
        }

        $this->parsed = true;

        if ('' === trim($this->xml)) {
            return $this->document = null;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new \DOMDocument();
            $loaded = @$document->loadXML($this->xml, LIBXML_NONET | LIBXML_PARSEHUGE);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $this->document = $loaded ? $document : null;
    }

    /**
     * Nombre local del elemento raíz (para diagnósticos).
     */
    public function rootName(): ?string
    {
        return $this->tryDocument()?->documentElement?->localName;
    }

    /**
     * `true` cuando el elemento raíz es `cfdi:Comprobante` de CFDI 3.3 o 4.0.
     */
    public function isCfdi(): bool
    {
        $root = $this->tryDocument()?->documentElement;

        if (!$root instanceof \DOMElement || 'Comprobante' !== $root->localName) {
            return false;
        }

        return \in_array($root->namespaceURI, self::CFD_NAMESPACES, true);
    }

    /**
     * Versión declarada del CFDI ("3.3", "4.0", …).
     */
    public function version(): ?string
    {
        return $this->rootAttribute('Version');
    }

    /**
     * UUID del comprobante cuando ya está timbrado, o `null` si aún no lo está.
     *
     * Finkok llama a este valor el «timbre previo»: si existe, `stamp()` responde
     * con la incidencia 307 y recupera el XML original.
     */
    public function uuid(): ?string
    {
        return $this->tfdAttribute('UUID');
    }

    /**
     * `true` cuando el XML ya contiene un nodo `tfd:TimbreFiscalDigital`.
     */
    public function hasStamp(): bool
    {
        return null !== $this->tfdElement();
    }

    /**
     * `true` cuando el comprobante ya está sellado (trae el atributo `Sello`).
     * Finkok rechaza con la incidencia CFDI40102 los CFDI sin sello.
     */
    public function hasSignature(): bool
    {
        return null !== $this->rootAttribute('Sello');
    }

    /**
     * Número de serie del certificado con el que se selló (`NoCertificado`).
     */
    public function certificateNumber(): ?string
    {
        return $this->rootAttribute('NoCertificado');
    }

    public function emitterRfc(): ?string
    {
        return $this->partyRfc('Emisor');
    }

    public function receiverRfc(): ?string
    {
        return $this->partyRfc('Receptor');
    }

    /**
     * Total del comprobante. Es el valor que hay que enviar como parámetro
     * `total` en `get_sat_status`: si no coincide con los decimales exactos del
     * CFDI, el SAT responde «N 601 La expresión impresa proporcionada no es válida».
     */
    public function total(): ?string
    {
        return $this->rootAttribute('Total');
    }

    /**
     * Sello del emisor (`Sello`), útil para la cadena original.
     */
    public function seal(): ?string
    {
        return $this->rootAttribute('Sello');
    }

    /**
     * Fecha de emisión declarada en el comprobante.
     */
    public function issuedAt(): ?string
    {
        return $this->rootAttribute('Fecha');
    }

    /**
     * Cuerpo XML sin la declaración `<?xml … ?>`, que algunas integraciones
     * necesitan al firmar o incrustar el comprobante.
     */
    public function contentWithoutDeclaration(): string
    {
        return (string) preg_replace('/^\s*<\?xml[^>]*\?>\s*/i', '', $this->xml);
    }

    /**
     * Resumen legible para logs y mensajes de error.
     */
    public function describe(): string
    {
        return sprintf(
            'CFDI %s | emisor %s | total %s | %d bytes | %s',
            $this->version() ?? '¿?',
            $this->emitterRfc() ?? '¿?',
            $this->total() ?? '¿?',
            $this->sizeInBytes(),
            match (true) {
                !$this->isWellFormed() => 'XML mal formado',
                $this->hasStamp() => 'ya timbrado (UUID '.($this->uuid() ?? '¿?').')',
                !$this->hasSignature() => 'sin sello',
                default => 'listo para timbrar',
            },
        );
    }

    public function __toString(): string
    {
        return $this->xml;
    }

    /**
     * Elemento raíz, o `null` si el XML no pudo parsearse.
     *
     * Los accesores de datos devuelven `null` en ese caso en lugar de lanzar
     * excepción, para que `describe()` y las comprobaciones previas puedan
     * informar sin romper el flujo. Quien necesite el DOM completo usa
     * `document()`, que sí lanza {@see ValidationException}.
     */
    private function rootElement(): ?\DOMElement
    {
        $root = $this->tryDocument()?->documentElement;

        return $root instanceof \DOMElement ? $root : null;
    }

    private function rootAttribute(string $name): ?string
    {
        $root = $this->rootElement();

        return null === $root ? null : DomReader::attribute($root, $name);
    }

    private function tfdElement(): ?\DOMElement
    {
        $document = $this->tryDocument();

        if (null === $document) {
            return null;
        }

        $matches = $document->getElementsByTagNameNS(self::TFD_NAMESPACE, 'TimbreFiscalDigital');

        if ($matches->length > 0 && $matches->item(0) instanceof \DOMElement) {
            return $matches->item(0);
        }

        return DomReader::descendant($document->documentElement, 'TimbreFiscalDigital');
    }

    private function tfdAttribute(string $name): ?string
    {
        $element = $this->tfdElement();

        return null === $element ? null : DomReader::attribute($element, $name);
    }

    private function partyRfc(string $party): ?string
    {
        $root = $this->rootElement();

        if (null === $root) {
            return null;
        }

        $element = DomReader::firstElement($root, $party);

        return null === $element ? null : DomReader::attribute($element, 'Rfc');
    }
}
