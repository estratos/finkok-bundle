<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Soap\Value;

use Finkok\CfdiBundle\Soap\SoapValueInterface;
use Finkok\CfdiBundle\Soap\SoapWriter;

/**
 * Contenido que **ya** está codificado en base64 y debe enviarse tal cual.
 *
 * Existe para hacer evidente la diferencia con {@see Base64Value}, que recibe
 * bytes crudos y los codifica. Confundirlos es la causa directa de la incidencia
 * 704 («el certificado o la llave se codificaron dos veces en base64») y de la
 * incidencia 705 en el XML del CFDI.
 *
 * Los parámetros `cer` y `key` del método `cancel` se construyen con este objeto,
 * porque {@see \Finkok\CfdiBundle\Csd\CsdEncoderInterface} ya entrega base64.
 */
final class Base64EncodedValue implements SoapValueInterface
{
    private function __construct(private readonly string $encoded)
    {
    }

    public static function fromEncoded(string $base64): self
    {
        return new self($base64);
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): \DOMElement
    {
        return $writer->textElement($parent, $name, $namespace, $this->encoded);
    }

    public function encoded(): string
    {
        return $this->encoded;
    }
}
