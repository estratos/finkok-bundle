<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Csd;

use Finkok\CfdiBundle\Exception\ValidationException;

/**
 * Codificador CSD por defecto: base64 del contenido del archivo, una sola vez.
 *
 * Es el comportamiento que espera la mayoría de las cuentas de Finkok y el que
 * evita la incidencia 704 (doble codificación en base64).
 *
 * Detalles de los archivos del SAT:
 *  - el `.cer` es un DER binario (PKCS#7); si se recibe en formato PEM se
 *    convierte a DER para no enviar la armadura de texto;
 *  - el `.key` es un DER binario cifrado (PKCS#8); se envía tal cual, sin
 *    descifrarlo, porque Finkok no recibe la contraseña por parámetro en el
 *    método `cancel`.
 *
 * Los valores aceptados pueden ser rutas de archivo o el contenido ya cargado
 * (útil cuando el CSD viene de un secret manager o de la base de datos).
 */
final class RawFileCsdEncoder implements CsdEncoderInterface
{
    public function encodeCertificate(string $pathOrPem): string
    {
        $contents = $this->read($pathOrPem, 'certificado (.cer)');

        if ($this->isPem($contents)) {
            $der = $this->pemToDer($contents);

            if (null !== $der) {
                return base64_encode($der);
            }
        }

        return base64_encode($contents);
    }

    public function encodePrivateKey(string $pathOrPem, ?string $passphrase = null): string
    {
        // La contraseña se acepta por compatibilidad con la interfaz; este
        // codificador no descifra la llave porque Finkok la recibe cifrada.
        $contents = $this->read($pathOrPem, 'llave privada (.key)');

        return base64_encode($contents);
    }

    /**
     * Carga el contenido desde una ruta o lo devuelve tal cual si ya es contenido.
     */
    private function read(string $pathOrContents, string $what): string
    {
        if ('' === trim($pathOrContents)) {
            throw new ValidationException(sprintf('El %s está vacío.', $what));
        }

        // Contenido ya cargado: un DER binario nunca es una ruta válida, pero un
        // PEM sí es texto reconocible.
        if ($this->isPem($pathOrContents)) {
            return $pathOrContents;
        }

        if (is_file($pathOrContents) && is_readable($pathOrContents)) {
            $contents = @file_get_contents($pathOrContents);

            if (false === $contents) {
                throw ValidationException::forUnreadableFile($pathOrContents, $what);
            }

            return $contents;
        }

        // No es un archivo: se asume contenido binario proporcionado en memoria.
        return $pathOrContents;
    }

    private function isPem(string $contents): bool
    {
        return str_contains($contents, '-----BEGIN ');
    }

    /**
     * Extrae el bloque DER de un PEM.
     */
    private function pemToDer(string $pem): ?string
    {
        if (1 !== preg_match('/-----BEGIN [^-]+-----(.+?)-----END [^-]+-----/s', $pem, $matches)) {
            return null;
        }

        $der = base64_decode(preg_replace('/\s+/', '', $matches[1]) ?? '', true);

        return false === $der ? null : $der;
    }
}
