<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Soap\Value;

use Finkok\CfdiBundle\Exception\ValidationException;
use Finkok\CfdiBundle\Soap\SoapValueInterface;

/**
 * Fábrica de valores SOAP a partir de escalares.
 *
 * Permite que los servicios declaren sus argumentos de forma natural
 * (`'username' => $credentials->username()`) sin obligar al consumidor a
 * envolver cada valor a mano.
 */
final class Value
{
    private function __construct()
    {
    }

    /**
     * Normaliza un valor arbitrario a un `SoapValueInterface`.
     *
     * Devuelve `null` para valores nulos o cadenas vacías, que se omiten del
     * envelope: los parámetros opcionales de Finkok son `minOccurs="0"` y enviar
     * un nodo vacío provoca la incidencia 705.
     */
    public static function of(mixed $value): ?SoapValueInterface
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof SoapValueInterface) {
            return $value;
        }

        if (\is_string($value) && '' === trim($value)) {
            return null;
        }

        if (\is_scalar($value)) {
            return new TextValue($value);
        }

        throw new ValidationException(sprintf(
            'No es posible serializar un valor de tipo "%s" en el envelope SOAP de Finkok.',
            get_debug_type($value),
        ));
    }

    /**
     * Normaliza un mapa de argumentos `nombre => valor`, descartando los que no
     * deben enviarse (`null` y cadenas vacías).
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, SoapValueInterface>
     */
    public static function map(array $arguments): array
    {
        $normalized = [];
        foreach ($arguments as $name => $value) {
            $normalizedValue = self::of($value);
            if (null !== $normalizedValue) {
                $normalized[$name] = $normalizedValue;
            }
        }

        return $normalized;
    }
}
