<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Xml;

/**
 * Utilidades de lectura sobre DOM tolerantes a prefijos y namespaces.
 *
 * Las respuestas de Finkok declaran los mismos tipos con prefijos distintos
 * según la versión del servidor (`s0`, `tns`, `ns1`, …), por lo que todo el
 * parseo del bundle se hace comparando el **nombre local** del nodo, nunca el
 * `nodeName` completo.
 *
 * La comparación es sensible a mayúsculas a propósito: el WSDL de Finkok
 * distingue `UUID` (acuse de timbrado) de `Uuid` (incidencia) y `uuid` (folio o
 * resultado de consulta).
 */
final class DomReader
{
    private function __construct()
    {
    }

    /**
     * Primer hijo directo cuyo nombre local coincida exactamente.
     */
    public static function firstElement(\DOMNode $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Todos los hijos directos cuyo nombre local coincida exactamente.
     *
     * @return list<\DOMElement>
     */
    public static function elements(\DOMNode $parent, string $localName): array
    {
        $found = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $found[] = $child;
            }
        }

        return $found;
    }

    /**
     * Todos los descendientes cuyo nombre local coincida, en orden de documento.
     *
     * @return list<\DOMElement>
     */
    public static function descendants(\DOMNode $parent, string $localName): array
    {
        $found = [];
        foreach ($parent->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $found[] = $child;
            }
        }

        return $found;
    }

    public static function descendant(\DOMNode $parent, string $localName): ?\DOMElement
    {
        return self::descendants($parent, $localName)[0] ?? null;
    }

    /**
     * Texto del primer hijo directo con ese nombre local, o `null` si el nodo no
     * existe o está vacío.
     */
    public static function textOf(\DOMNode $parent, string $localName): ?string
    {
        $element = self::firstElement($parent, $localName);

        return null === $element ? null : self::text($element);
    }

    /**
     * Contenido textual normalizado de un nodo, o `null` si queda vacío.
     *
     * Se normalizan los retornos de carro porque el XML timbrado puede venir con
     * `\r\n` inyectados por un proxy y esos caracteres rompen la validación
     * posterior del CFDI.
     */
    public static function text(\DOMNode $node): ?string
    {
        $text = $node->textContent;

        if ('' === $text) {
            return null;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        return '' === $text ? null : $text;
    }

    /**
     * Texto crudo sin normalizar, para contenido que debe conservarse byte a byte
     * (por ejemplo un XML timbrado que ya se validó antes).
     */
    public static function rawText(\DOMNode $node): ?string
    {
        $text = trim($node->textContent);

        return '' === $text ? null : $text;
    }

    /**
     * Texto de un nodo que contiene un XML.
     *
     * Algunas respuestas de Finkok entregan el comprobante escapado dos veces
     * (el XML llega como `&lt;?xml …&gt;`). Se detecta ese caso —y solo ese— para
     * no corromper las entidades legítimas del CFDI (`&amp;`, `&quot;`) cuando el
     * XML está bien formado.
     */
    public static function xmlText(\DOMNode $node): ?string
    {
        $text = self::rawText($node);

        if (null === $text) {
            return null;
        }

        if (str_starts_with($text, '&lt;')) {
            $decoded = html_entity_decode($text, \ENT_QUOTES | \ENT_XML1, 'UTF-8');

            if (str_contains($decoded, '<')) {
                return trim($decoded);
            }
        }

        return $text;
    }

    public static function hasChild(\DOMNode $parent, string $localName): bool
    {
        return null !== self::firstElement($parent, $localName);
    }

    /**
     * Atributo sin namespace, o `null` si no existe o está vacío.
     */
    public static function attribute(\DOMElement $element, string $name): ?string
    {
        if (!$element->hasAttribute($name)) {
            return null;
        }

        $value = trim($element->getAttribute($name));

        return '' === $value ? null : $value;
    }

    public static function booleanAttribute(\DOMElement $element, string $name): ?bool
    {
        $value = self::attribute($element, $name);

        return null === $value ? null : \in_array(strtolower($value), ['true', '1', 'yes', 'si', 'sí'], true);
    }

    /**
     * Interpreta los valores booleanos tal como los serializa SOAP
     * (`true` / `false` / `1` / `0`).
     */
    public static function toBoolean(?string $value): ?bool
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        return \in_array(strtolower(trim($value)), ['true', '1', 'yes', 'si', 'sí'], true);
    }

    /**
     * Convierte un entero seguro desde texto de respuesta.
     */
    public static function toInt(?string $value): ?int
    {
        if (null === $value || '' === trim($value) || !is_numeric(trim($value))) {
            return null;
        }

        return (int) trim($value);
    }

    /**
     * Carga un documento XML desde una cadena sin emitir warnings al exterior.
     *
     * @throws \Estratos\FinkokBundle\Exception\UnexpectedResponseException
     */
    public static function loadDocument(string $xml, string $context): \DOMDocument
    {
        $document = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $loaded = '' !== trim($xml) && @$document->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded || [] !== $errors) {
            $detail = [] !== $errors ? trim($errors[0]->message) : 'contenido vacío';

            throw new \Estratos\FinkokBundle\Exception\UnexpectedResponseException(
                sprintf('No fue posible interpretar el XML de %s: %s', $context, $detail),
                null,
                null,
                \strlen($xml) > 2000 ? substr($xml, 0, 2000).'…' : $xml,
            );
        }

        return $document;
    }
}
