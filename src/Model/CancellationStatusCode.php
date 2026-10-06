<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

/**
 * Valores de `CodEstatus` que devuelve el método `cancel`.
 *
 * Ojo: estos códigos **no** son los mismos que `EstatusUUID` (el código con el
 * que responde el SAT por cada UUID) ni que los `CodigoError` de las
 * incidencias. Aquí se tipifican los códigos propios del servicio de
 * cancelación de Finkok, documentados en su centro de soporte.
 *
 * Advertencia clave de Finkok: el código 201 confirma que la **petición** se
 * realizó correctamente, pero **no** garantiza que el comprobante ya esté
 * cancelado. Siempre hay que confirmar con `get_sat_status`.
 */
enum CancellationStatusCode: string
{
    /** Petición de cancelación realizada exitosamente. */
    case RequestAccepted = '201';

    /** Petición de cancelación realizada previamente. */
    case RequestAlreadySent = '202';

    /** No corresponde el RFC del Emisor y de quien solicita la cancelación. */
    case IssuerMismatch = '203';

    /** UUID no encontrado. */
    case UuidNotFound = '205';

    /** Certificado revocado o caduco. */
    case RevokedOrExpiredCertificate = '304';

    /** El certificado o la llave se codificaron dos veces en base64. */
    case DoubleBase64Encoding = '704';

    /** Ya existe una solicitud previa (respuesta 201, 202 o ya cancelado). */
    case AlreadyRequested = '798';

    /** Se excedió el límite de 5 peticiones de cancelación. */
    case TooManyAttempts = '799';

    public function description(): string
    {
        return match ($this) {
            self::RequestAccepted => 'Petición de cancelación realizada exitosamente.',
            self::RequestAlreadySent => 'Petición de cancelación realizada previamente.',
            self::IssuerMismatch => 'No corresponde el RFC del Emisor y de quien solicita la cancelación.',
            self::UuidNotFound => 'UUID no encontrado.',
            self::RevokedOrExpiredCertificate => 'Certificado revocado o caduco.',
            self::DoubleBase64Encoding => 'El certificado o la llave se enviaron doblemente codificados en base64.',
            self::AlreadyRequested => 'Ya existe una solicitud previa de cancelación para este UUID.',
            self::TooManyAttempts => 'Se excedieron las 5 peticiones de cancelación permitidas para este UUID.',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::RequestAccepted => 'La petición fue aceptada, pero confirma la cancelación definitiva con get_sat_status().',
            self::RequestAlreadySent => 'No vuelvas a enviar la petición: consulta el estatus ante el SAT con get_sat_status().',
            self::IssuerMismatch => 'El parámetro taxpayer_id debe ser el RFC del Emisor del CFDI a cancelar.',
            self::UuidNotFound => 'Verifica el UUID y espera unos minutos si el CFDI se acaba de timbrar; el SAT puede no tenerlo registrado todavía.',
            self::RevokedOrExpiredCertificate => 'Usa un CSD vigente; no es necesario que sea el mismo con el que se timbró el CFDI.',
            self::DoubleBase64Encoding => 'Envía el contenido del archivo .cer/.key codificado en base64 una sola vez.',
            self::AlreadyRequested => 'Consulta el estatus; si sigue vigente, espera 72 horas antes de reintentar.',
            self::TooManyAttempts => 'Ya no es posible cancelar por este medio: contacta a soporte de Finkok.',
        };
    }

    /**
     * `true` cuando la petición fue recibida por Finkok (aunque no confirme la
     * cancelación definitiva).
     */
    public function isRequestAccepted(): bool
    {
        return match ($this) {
            self::RequestAccepted, self::RequestAlreadySent, self::AlreadyRequested => true,
            default => false,
        };
    }

    /**
     * `true` cuando el código se resuelve reintentando más tarde.
     */
    public function isTransient(): bool
    {
        return match ($this) {
            self::UuidNotFound, self::AlreadyRequested => true,
            default => false,
        };
    }

    /**
     * `true` cuando la incidencia se resuelve desde el panel de Finkok o
     * contactando a soporte.
     */
    public function requiresManualAction(): bool
    {
        return match ($this) {
            self::TooManyAttempts, self::RevokedOrExpiredCertificate => true,
            default => false,
        };
    }
}
