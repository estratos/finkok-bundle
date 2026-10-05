<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Soap\Value;

use Finkok\CfdiBundle\Soap\SoapValueInterface;
use Finkok\CfdiBundle\Soap\SoapWriter;

/**
 * Elemento contenedor con hijos repetidos del mismo nombre.
 *
 * Corresponde a los arreglos del WSDL de Finkok:
 *  - `UUIDArray` → elemento `UUID` repetido (cancelación);
 *  - `stringArray` → elemento `string` repetido (resultado de `get_pending`);
 *  - `UUID_ARArray` → elemento `UUID_AR` repetido (aceptación/rechazo).
 *
 * ```php
 * new RepeatedValue('UUID', $items, 'apps.services.soap.core.views');
 * ```
 */
final class RepeatedValue implements SoapValueInterface
{
    /** @var list<SoapValueInterface> */
    private readonly array $items;

    /**
     * @param iterable<mixed> $items           valores o `SoapValueInterface` de cada elemento
     * @param string|null     $itemNamespace   namespace de los hijos; si es `null`
     *                                         se usa el del contenedor
     */
    public function __construct(
        private readonly string $itemName,
        iterable $items,
        private readonly ?string $itemNamespace = null,
    ) {
        $normalized = [];
        foreach ($items as $item) {
            $value = Value::of($item);
            if (null !== $value) {
                $normalized[] = $value;
            }
        }

        $this->items = $normalized;
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): \DOMElement
    {
        $element = $writer->element($parent, $name, $namespace);
        $itemNamespace = $this->itemNamespace ?? $namespace;

        foreach ($this->items as $item) {
            $item->write($writer, $element, $this->itemName, $itemNamespace);
        }

        return $element;
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }
}
