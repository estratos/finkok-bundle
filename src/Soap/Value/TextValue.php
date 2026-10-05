<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Soap\Value;

use Finkok\CfdiBundle\Soap\SoapValueInterface;
use Finkok\CfdiBundle\Soap\SoapWriter;

/**
 * Valor textual simple (`xs:string`, `xs:int`, `xs:boolean`, …).
 *
 * Los booleanos se serializan como `true` / `false`, que es lo que espera el
 * esquema SOAP para `xs:boolean`.
 */
final class TextValue implements SoapValueInterface
{
    private readonly string $text;

    public function __construct(string|int|float|bool $value)
    {
        $this->text = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): \DOMElement
    {
        return $writer->textElement($parent, $name, $namespace, $this->text);
    }

    public function text(): string
    {
        return $this->text;
    }
}
