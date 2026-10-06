<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Soap;

/**
 * Constructor de documentos XML con gestión automática de prefijos.
 *
 * Los WSDL de Finkok son document/literal con `elementFormDefault="qualified"`,
 * así que cada elemento debe declararse en el namespace correcto. Este writer
 * asigna y reutiliza prefijos estables (`soap`, `tns`, `s0`, `xsi`, …) para que
 * el envelope resultante sea determinista y legible en los logs.
 */
final class SoapWriter
{
    /** Prefijos preferidos por namespace, para que la salida sea estable. */
    private const PREFERRED_PREFIXES = [
        'http://schemas.xmlsoap.org/soap/envelope/' => 'soap',
        'apps.services.soap.core.views' => 's0',
        'http://www.w3.org/2001/XMLSchema-instance' => 'xsi',
        'http://www.w3.org/2001/XMLSchema' => 'xsd',
    ];

    private readonly \DOMDocument $document;

    /** @var array<string, string> namespace => prefijo */
    private array $prefixes = [];

    private int $generated = 0;

    public function __construct()
    {
        $this->document = new \DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = false;
        $this->document->preserveWhiteSpace = false;
    }

    public function document(): \DOMDocument
    {
        return $this->document;
    }

    /**
     * Prefijo asociado a un namespace, generándolo si es la primera vez que se ve.
     */
    public function prefixFor(string $namespace): string
    {
        if (isset($this->prefixes[$namespace])) {
            return $this->prefixes[$namespace];
        }

        $prefix = self::PREFERRED_PREFIXES[$namespace] ?? null;

        if (null === $prefix || \in_array($prefix, $this->prefixes, true)) {
            do {
                $prefix = 'ns'.(++$this->generated);
            } while (\in_array($prefix, $this->prefixes, true));
        }

        return $this->prefixes[$namespace] = $prefix;
    }

    /**
     * Fija manualmente el prefijo de un namespace (por ejemplo el `tns` del
     * servicio) antes de construir los elementos.
     */
    public function preferPrefix(string $namespace, string $prefix): void
    {
        $this->prefixes[$namespace] = $prefix;
    }

    public function qualifiedName(string $namespace, string $name): string
    {
        return $this->prefixFor($namespace).':'.$name;
    }

    /**
     * Crea un elemento vacío y lo agrega al padre.
     */
    public function element(\DOMElement $parent, string $name, string $namespace): \DOMElement
    {
        $element = $this->document->createElementNS($namespace, $this->qualifiedName($namespace, $name));
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Crea el elemento raíz del documento.
     */
    public function root(string $name, string $namespace): \DOMElement
    {
        $element = $this->document->createElementNS($namespace, $this->qualifiedName($namespace, $name));
        $this->document->appendChild($element);

        return $element;
    }

    /**
     * Crea un elemento con contenido textual, escapando lo necesario.
     */
    public function textElement(\DOMElement $parent, string $name, string $namespace, string $text): \DOMElement
    {
        $element = $this->element($parent, $name, $namespace);

        if ('' !== $text) {
            $element->appendChild($this->document->createTextNode($text));
        }

        return $element;
    }

    /**
     * Agrega un atributo sin namespace (los atributos de los tipos de Finkok no
     * están calificados).
     */
    public function attribute(\DOMElement $element, string $name, string $value): void
    {
        $element->setAttribute($name, $value);
    }

    /**
     * Serializa el documento completo.
     */
    public function toXml(): string
    {
        $xml = $this->document->saveXML();

        return false === $xml ? '' : $xml;
    }
}
