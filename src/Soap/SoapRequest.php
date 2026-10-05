<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Soap;

use Finkok\CfdiBundle\Soap\Value\Base64EncodedValue;
use Finkok\CfdiBundle\Soap\Value\Base64Value;
use Finkok\CfdiBundle\Soap\Value\Value;

/**
 * Petición SOAP completa: endpoint, operación, namespace y argumentos.
 *
 * Se construye con `toXml()` y no conoce el cliente HTTP, lo que permite
 * inspeccionar o registrar el envelope sin abrir una conexión (muy útil para
 * depurar incidencias 705/738 o para pruebas de contrato).
 */
final class SoapRequest
{
    /**
     * @param array<string, mixed> $arguments argumentos en el orden exacto del WSDL
     * @param float|null           $timeout   segundos; `null` usa el timeout global
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly string $operation,
        public readonly string $namespace,
        public readonly array $arguments = [],
        public readonly ?float $timeout = null,
    ) {
    }

    /**
     * Valor de la cabecera HTTP `SOAPAction`. Los WSDL de Finkok declaran
     * `soapAction` igual al nombre de la operación.
     */
    public function soapAction(): string
    {
        return $this->operation;
    }

    /**
     * Construye el envelope SOAP 1.1 document/literal.
     */
    public function toXml(): string
    {
        $writer = new SoapWriter();
        $writer->preferPrefix(SoapNamespaces::ENVELOPE, 'soap');
        $writer->preferPrefix(SoapNamespaces::XSI, 'xsi');
        $writer->preferPrefix(SoapNamespaces::VIEWS, 's0');
        $writer->preferPrefix($this->namespace, 'tns');

        $envelope = $writer->root('Envelope', SoapNamespaces::ENVELOPE);
        $body = $writer->element($envelope, 'Body', SoapNamespaces::ENVELOPE);
        $operation = $writer->element($body, $this->operation, $this->namespace);

        foreach (Value::map($this->arguments) as $name => $value) {
            $value->write($writer, $operation, $name, $this->namespace);
        }

        return $writer->toXml();
    }

    /**
     * Vista previa legible de los argumentos, con los valores sensibles
     * enmascarados y los binarios resumidos. Se usa para logs.
     *
     * @return array<string, string>
     */
    public function debugArguments(): array
    {
        $debug = [];
        foreach ($this->arguments as $name => $value) {
            $debug[$name] = match (true) {
                'password' === $name => '***',
                $value instanceof Base64Value => sprintf('<base64 de %d bytes>', \strlen($value->binary())),
                $value instanceof Base64EncodedValue => sprintf('<base64 de %d caracteres>', \strlen($value->encoded())),
                $value instanceof SoapValueInterface => '<'.\get_debug_type($value).'>',
                \is_bool($value) => $value ? 'true' : 'false',
                null === $value => 'null',
                \is_scalar($value) => \strlen((string) $value) > 64
                    ? sprintf('<%d caracteres>', \strlen((string) $value))
                    : (string) $value,
                default => '<'.\get_debug_type($value).'>',
            };
        }

        return $debug;
    }
}
