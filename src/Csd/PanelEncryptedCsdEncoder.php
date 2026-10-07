<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Csd;

use Estratos\FinkokBundle\Exception\ValidationException;

/**
 * Codificador CSD por defecto: reproduce el proceso que documenta Finkok.
 *
 * Para la llave privada: se abre con su propia contraseña, se vuelve a exportar
 * cifrada en DES3 con la contraseña del panel y se codifica en base64 una sola
 * vez. Para el certificado: se envía en base64 **incluyendo los encabezados PEM**.
 *
 * Nota sobre el formato: OpenSSL 3 (el que trae PHP 8.2+) exporta la llave
 * cifrada como PKCS#8 (`-----BEGIN ENCRYPTED PRIVATE KEY-----`), mientras que
 * `openssl rsa -des3` la escribe en el formato tradicional con `DEK-Info`. Ambos
 * son PEM cifrados en 3DES con la misma contraseña y cualquier lector estándar
 * los acepta; si tu cuenta exigiera el formato tradicional, prepara el archivo
 * con el comando de Finkok y registra un codificador propio.
 *
 * Entradas admitidas: ruta de archivo o contenido ya cargado, en PEM o en DER
 * (el `.cer` y el `.key` que entrega el SAT son DER).
 */
final class PanelEncryptedCsdEncoder implements CsdEncoderInterface
{
    /** Cabeceras PEM posibles para una llave privada recibida en DER. */
    private const KEY_HEADERS = ['ENCRYPTED PRIVATE KEY', 'PRIVATE KEY', 'RSA PRIVATE KEY'];

    public function encodeCertificate(string $certificate): string
    {
        $contents = $this->read($certificate, 'certificado (.cer)');

        // El error 711 aparece cuando el base64 no lleva los encabezados, así que
        // se envía el PEM completo y no el DER.
        return base64_encode($this->asPem($contents, 'CERTIFICATE'));
    }

    public function encodePrivateKey(string $privateKey, ?string $keyPassphrase, string $panelPassword): string
    {
        if ('' === trim($panelPassword)) {
            throw new ValidationException(
                'Hace falta la contraseña del panel de Finkok para cifrar la llave privada en DES3.',
            );
        }

        $contents = $this->read($privateKey, 'llave privada (.key)');
        $resource = $this->openPrivateKey($contents, $keyPassphrase);

        $encrypted = '';
        $exported = @openssl_pkey_export($resource, $encrypted, $panelPassword, [
            'encrypt_key' => true,
            'encrypt_key_cipher' => OPENSSL_CIPHER_3DES,
        ]);

        if (!$exported || '' === $encrypted) {
            throw new ValidationException(sprintf(
                'No fue posible cifrar la llave privada en DES3: %s',
                $this->lastOpenSslError(),
            ));
        }

        return base64_encode($encrypted);
    }

    /**
     * Abre la llave privada admitiendo PEM o DER.
     *
     * El DER del SAT puede venir en tres formatos distintos, así que se prueban
     * las cabeceras posibles en lugar de exigir una.
     */
    private function openPrivateKey(string $contents, ?string $keyPassphrase): \OpenSSLAsymmetricKey
    {
        foreach ($this->candidatePems($contents) as $candidate) {
            $resource = @openssl_pkey_get_private($candidate, $keyPassphrase);

            if (false !== $resource) {
                return $resource;
            }
        }

        throw new ValidationException(
            'No fue posible abrir la llave privada. Verifica que el archivo corresponda a un CSD, '
            .'que su contraseña sea la correcta y que la codificación no esté dañada.',
        );
    }

    /**
     * @return list<string>
     */
    private function candidatePems(string $contents): array
    {
        if ($this->isPem($contents)) {
            return [$contents];
        }

        $pems = [];
        foreach (self::KEY_HEADERS as $header) {
            $pems[] = $this->asPem($contents, $header);
        }

        return $pems;
    }

    private function asPem(string $contents, string $header): string
    {
        if ($this->isPem($contents)) {
            return $contents;
        }

        return sprintf(
            "-----BEGIN %s-----\n%s-----END %s-----\n",
            $header,
            chunk_split(base64_encode($contents), 64, "\n"),
            $header,
        );
    }

    /**
     * Carga el contenido desde una ruta o lo devuelve tal cual si ya es contenido.
     */
    private function read(string $pathOrContents, string $what): string
    {
        if ('' === trim($pathOrContents)) {
            throw new ValidationException(sprintf('El %s está vacío.', $what));
        }

        if ($this->isPem($pathOrContents)) {
            return $pathOrContents;
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

    private function isPem(string $contents): bool
    {
        return str_contains($contents, '-----BEGIN ');
    }

    private function lastOpenSslError(): string
    {
        $error = openssl_error_string();

        return false === $error ? 'error desconocido de OpenSSL' : $error;
    }
}