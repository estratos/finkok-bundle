<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Soap\Value;

use Finkok\CfdiBundle\Soap\SoapValueInterface;
use Finkok\CfdiBundle\Soap\SoapWriter;

/**
 * Elemento complejo con hijos nombrados.
 *
 * Se usa para argumentos cuyo tipo en el WSDL es un `complexType` con secuencia,
 * por ejemplo `UUID_AR` (`uuid` + `respuesta`) del método `accept_reject`.
 *
 * ```php
 * new ComplexValue(['uuid' => $uuid, 'respuesta' => 'Aceptacion']);
 * ```
 */
final class ComplexValue implements SoapValueInterface
{
    /** @var array<string, SoapValueInterface> */
    private readonly array $children;

    /**
     * @param array<string, mixed> $children        nombre del elemento hijo => valor
     * @param string|null          $childNamespace  namespace de los hijos; si es
     *                                              `null` se usa el del propio elemento
     */
    public function __construct(array $children, private readonly ?string $childNamespace = null)
    {
        $this->children = Value::map($children);
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): \DOMElement
    {
        $element = $writer->element($parent, $name, $namespace);
        $childNamespace = $this->childNamespace ?? $namespace;

        foreach ($this->children as $childName => $child) {
            $child->write($writer, $element, $childName, $childNamespace);
        }

        return $element;
    }
}
