<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Csd;

/**
 * Convierte archivos CSD (`.cer` / `.key`) al valor exacto que espera el
 * Web Service de cancelación de Finkok.
 *
 * Se modela como interfaz porque Finkok ha documentado más de un proceso según
 * la antigüedad de la cuenta:
 *
 * 1. **Base64 directo** (comportamiento por defecto del bundle, ver
 *    {@see RawFileCsdEncoder}): se codifica el contenido del archivo una sola vez.
 * 2. **PEM + cifrado DES3 con la contraseña del panel**: Finkok documenta para
 *    algunas cuentas convertir la llave a PEM con su propia contraseña,
 *    cifrarla con la contraseña de acceso al panel y hasta entonces codificarla
 *    en base64. Si tu cuenta requiere este proceso (síntoma: el servicio responde
 *    «Invalid Passphrase» o la incidencia 704), implementa esta interfaz y
 *    registra tu implementación como servicio `finkok.csd_encoder`.
 *
 * En ambos casos la regla de oro es codificar **una sola vez**: la doble
 * codificación en base64 produce la incidencia 704.
 */
interface CsdEncoderInterface
{
    /**
     * Valor para el parámetro `cer`.
     *
     * @param string $pathOrPem ruta al archivo o su contenido ya cargado
     */
    public function encodeCertificate(string $pathOrPem): string;

    /**
     * Valor para el parámetro `key`.
     *
     * @param string      $pathOrPem   ruta al archivo o su contenido ya cargado
     * @param string|null $passphrase  contraseña propia de la llave, si aplica
     */
    public function encodePrivateKey(string $pathOrPem, ?string $passphrase = null): string;
}
