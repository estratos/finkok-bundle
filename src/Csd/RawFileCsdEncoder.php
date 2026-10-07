<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Csd;

use Estratos\FinkokBundle\Exception\ValidationException;

/**
 * Codificador alternativo: base64 del archivo tal cual, sin cifrado.
 *
 * **No** implementa el proceso que documenta Finkok —PEM + cifrado DES3 con la
 * contraseña del panel—, que es el que reproduce
 * {@see PanelEncryptedCsdEncoder} y el que espera el Web Service. Se conserva
 * como salida de emergencia para cuentas en las que Finkok acepta el base64
 * directo: si al cancelar recibes «Invalid Passphrase», «Incorrect padding» o el
 * error 711, el codificador que necesitas es el de por defecto, no este.
 *
 * Ventaja puntual: no descifra la llave, así que no necesita la contraseña del
 * CSD ni la del panel.
 */
final class RawFileCsdEncoder implements CsdEncoderInterface
{
    public function encodeCertificate(string $certificate): string
    {
        return base64_encode($this->read($certificate, 'certificado (.cer)'));
    }

    public function encodePrivateKey(string $privateKey, ?string $keyPassphrase, string $panelPassword): string
    {
        return base64_encode($this->read($privateKey, 'llave privada (.key)'));
    }

    /**
     * Carga el contenido desde una ruta o lo devuelve tal cual si ya es contenido.
     */
    private function read(string $pathOrContents, string $what): string
    {
        if ('' === trim($pathOrContents)) {
            throw new ValidationException(sprintf('El %s está vacío.', $what));
        }

        // Solo se consulta el sistema de archivos si el valor puede ser una ruta:
        // el contenido DER es binario y una ruta no lleva saltos de línea.
        if (!str_contains($pathOrContents, "\n") && @is_file($pathOrContents) && is_readable($pathOrContents)) {
            $contents = @file_get_contents($pathOrContents);

            if (false === $contents) {
                throw ValidationException::forUnreadableFile($pathOrContents, $what);
            }

            return $contents;
        }

        return $pathOrContents;
    }
}