<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Soap;

/**
 * Valor tipado que puede escribirse dentro de un envelope SOAP.
 *
 * Finkok mezcla en sus firmas tipos simples (`xs:string`, `xs:boolean`),
 * binarios (`xs:base64Binary`) y tipos complejos (`UUIDArray`, `UUIDS_AR`), por
 * lo que los argumentos se modelan como objetos y no como arreglos sueltos.
 */
interface SoapValueInterface
{
    /**
     * Escribe este valor como elemento `$name` en `$namespace` dentro de `$parent`.
     *
     * @return \DOMElement el elemento recién creado
     */
    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): \DOMElement;
}
