<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Soap\Value;

use Estratos\FinkokBundle\Soap\SoapValueInterface;
use Estratos\FinkokBundle\Soap\SoapWriter;

/**
 * Valor binario codificado en base64 (`xs:base64Binary`).
 *
 * Finkok recibe el XML del CFDI y los archivos CSD (`.cer` / `.key`) como
 * base64Binary. Un error recurrente —y causa directa de la incidencia 705— es
 * enviar un XML ya codificado en base64 y volverlo a codificar; este objeto
 * recibe siempre bytes crudos y hace la codificación una sola vez.
 */
final class Base64Value implements SoapValueInterface
{
    private readonly string $binary;

    public function __construct(string $binary)
    {
        $this->binary = $binary;
    }

    public static function fromBinary(string $binary): self
    {
        return new self($binary);
    }

    /**
     * Codifica el contenido de un archivo local.
     */
    public static function fromFile(string $path): self
    {
        $contents = @file_get_contents($path);

        if (false === $contents) {
            throw new \Estratos\FinkokBundle\Exception\ValidationException(
                sprintf('No fue posible leer el archivo binario "%s".', $path),
            );
        }

        return new self($contents);
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): \DOMElement
    {
        return $writer->textElement($parent, $name, $namespace, $this->encoded());
    }

    public function encoded(): string
    {
        return base64_encode($this->binary);
    }

    public function binary(): string
    {
        return $this->binary;
    }
}
