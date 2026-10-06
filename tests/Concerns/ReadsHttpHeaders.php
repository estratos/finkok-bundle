<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Concerns;

/**
 * Lectura de las cabeceras HTTP tal como las entrega `MockHttpClient`.
 *
 * Symfony puede representarlas como mapa (`['SOAPAction' => '"stamp"']`) o como
 * lista (`['SOAPAction: "stamp"']`) según cómo se hayan construido las opciones,
 * así que las pruebas aceptan ambas formas.
 */
trait ReadsHttpHeaders
{
    /**
     * @param array<string, mixed> $options
     */
    private function headerOf(array $options, string $name): ?string
    {
        foreach ($this->headersMap($options) as $headerName => $value) {
            if (0 === strcasecmp($headerName, $name)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, string>
     */
    private function headersMap(array $options): array
    {
        $headers = $options['headers'] ?? [];

        if (!\is_array($headers)) {
            return [];
        }

        $map = [];
        foreach ($headers as $key => $value) {
            if (\is_string($key)) {
                $map[$key] = \is_array($value) ? (string) reset($value) : (string) $value;

                continue;
            }

            if (\is_string($value) && str_contains($value, ':')) {
                [$headerName, $headerValue] = explode(':', $value, 2);
                $map[trim($headerName)] = trim($headerValue);
            }
        }

        return $map;
    }
}
