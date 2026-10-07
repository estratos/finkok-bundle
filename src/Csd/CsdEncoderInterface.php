<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Csd;

/**
 * Convierte archivos CSD (`.cer` / `.key`) al valor exacto que espera el
 * Web Service de cancelación de Finkok.
 *
 * El proceso está documentado por Finkok y es el siguiente:
 *
 * 1. La llave se convierte a PEM con **su propia** contraseña.
 * 2. Ese PEM se cifra en **DES3 con la contraseña del panel de Finkok**.
 * 3. El resultado se codifica en base64 y se envía como parámetro `key`.
 *
 * Que equivale al comando publicado por Finkok:
 *
 * ```bash
 * openssl rsa -in RFC.key.pem -des3 -out RFC.enc -passout pass:"su contraseña"
 * ```
 *
 * Reglas que evitan incidencias ya documentadas:
 *
 *  - codificar **una sola vez** en base64: la doble codificación produce el
 *    error 704;
 *  - enviar el certificado en base64 **con sus encabezados PEM**, porque el
 *    error 711 aparece cuando «al momento de codificarlo a base64 no contiene
 *    los encabezados».
 *
 * La implementación por defecto es {@see PanelEncryptedCsdEncoder}. Se modela
 * como interfaz para que una cuenta con requisitos distintos pueda sustituirla
 * registrando su propio servicio `finkok.csd_encoder`.
 */
interface CsdEncoderInterface
{
    /**
     * Valor para el parámetro `cer`.
     *
     * @param string $certificate ruta al archivo `.cer` o su contenido
     */
    public function encodeCertificate(string $certificate): string;

    /**
     * Valor para el parámetro `key`.
     *
     * @param string      $privateKey      ruta al archivo `.key` o su contenido
     * @param string|null $keyPassphrase   contraseña propia de la llave
     * @param string      $panelPassword   contraseña del panel de Finkok, con la
     *                                     que Finkok descifrará la llave
     */
    public function encodePrivateKey(string $privateKey, ?string $keyPassphrase, string $panelPassword): string;
}