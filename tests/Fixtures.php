<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests;

/**
 * Acceso a los archivos de prueba.
 *
 * Las respuestas de `tests/Fixtures/responses/` reproducen la forma real de los
 * envelopes de Finkok (incluyendo los prefijos `senv`, `tns` y `s0`, y los
 * valores textuales de `CodEstatus`), de modo que las pruebas validen el parseo
 * y no una versión idealizada.
 */
final class Fixtures
{
    public static function path(string $relativePath): string
    {
        return __DIR__.'/Fixtures/'.$relativePath;
    }

    public static function contents(string $relativePath): string
    {
        $contents = @file_get_contents(self::path($relativePath));

        if (false === $contents) {
            throw new \RuntimeException(sprintf('No existe el fixture "%s".', $relativePath));
        }

        return $contents;
    }

    /**
     * CFDI de prueba: firmado y sin timbre, listo para timbrar.
     */
    public static function signedCfdi(): string
    {
        return self::contents('cfdi-4.0-signed.xml');
    }

    /**
     * CFDI de prueba que ya contiene un TimbreFiscalDigital.
     */
    public static function stampedCfdi(): string
    {
        return self::contents('cfdi-4.0-stamped.xml');
    }

    /**
     * Envelope SOAP de respuesta de Finkok.
     */
    public static function response(string $name): string
    {
        return self::contents('responses/'.$name.'.xml');
    }
}
