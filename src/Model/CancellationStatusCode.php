<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

/**
 * Códigos que devuelven los métodos de cancelación de Finkok.
 *
 * Se usan en dos lugares distintos del mismo resultado:
 *
 *  - CodEstatus del acuse (nivel petición) y
 *  - EstatusUUID de cada Folio (nivel UUID).
 *
 * El significado de un código no cambia entre ambos, así que se tipifican en un
 * solo enum. Proceden de las tablas de validación publicadas por Finkok:
 * 201-212 y 
o_cancelable, las validaciones de petición 300-314, y los errores
 * propios de Finkok 704, 708, 711, 798 y 799.
 *
 * Advertencia clave: el 201 confirma que la **petición** se realizó, no que el CFDI
 * esté cancelado. Hay que confirmarlo con get_sat_status().
 */
enum CancellationStatusCode: string
{
    // --- Validación de la cancelación del CFDI (201-212) ---

    /** Petición de cancelación realizada exitosamente. */
    case RequestAccepted = '201';

    /** UUID previamente cancelado. */
    case PreviouslyCancelled = '202';

    /** No encontrado o no corresponde en el emisor. */
    case NotFoundOrIssuerMismatch = '203';

    /** UUID no aplicable para cancelación. */
    case NotApplicable = '204';

    /** UUID no existe. */
    case UuidNotFound = '205';

    /** UUID no corresponde a un CFDI del sector primario. */
    case NotPrimarySector = '206';

    /** Motivo de cancelación inválido. */
    case InvalidReason = '207';

    /** Folio de sustitución inválido. */
    case InvalidReplacementFolio = '208';

    /** Folio de sustitución no requerido. */
    case ReplacementFolioNotRequired = '209';

    /** La fecha de solicitud de cancelación es mayor a la fecha de declaración. */
    case RequestDateAfterDeclaration = '210';

    /** La fecha de solicitud rebasa el límite para una factura global. */
    case GlobalInvoiceDateLimit = '211';

    /** Relación no válida o inexistente. */
    case InvalidRelation = '212';

    /** El UUID contiene CFDI relacionados. */
    case NotCancellable = 'no_cancelable';

    // --- Validación en las peticiones (300-314) ---

    /** Usuario no válido. */
    case InvalidUser = '300';

    /** XML mal formado. */
    case MalformedXml = '301';

    /** Sello mal formado. */
    case MalformedSeal = '302';

    /** Certificado revocado o caduco. */
    case RevokedOrExpiredCertificate = '304';

    /** Certificado inválido. */
    case InvalidCertificate = '305';

    /** Patrón de folio inválido. */
    case InvalidFolioPattern = '309';

    /** Se está usando un certificado tipo FIEL y no de CSD. */
    case FielInsteadOfCsd = '310';

    /** Clave de motivo de cancelación no válida. */
    case InvalidReasonKey = '311';

    /** UUID no relacionado de acuerdo a la clave de motivo de cancelación. */
    case UuidNotRelatedToReason = '312';

    /** Relación no válida. */
    case RelationNotValid = '314';

    // --- Errores propios de Finkok ---

    /** El certificado o la llave se enviaron doblemente codificados en base64. */
    case DoubleBase64Encoding = '704';

    /** No se pudo conectar al SAT. */
    case SatUnreachable = '708';

    /** Error con el certificado al cancelar. */
    case CertificateError = '711';

    /** Ya existe una solicitud previa; hay que esperar 72 horas. */
    case AlreadyRequested = '798';

    /** Se excedió el límite de 5 peticiones de cancelación. */
    case TooManyAttempts = '799';

    public function description(): string
    {
        return match ($this) {
            self::RequestAccepted => 'Petición de cancelación realizada exitosamente.',
            self::PreviouslyCancelled => 'UUID previamente cancelado.',
            self::NotFoundOrIssuerMismatch => 'No encontrado o no corresponde en el emisor.',
            self::NotApplicable => 'UUID no aplicable para cancelación.',
            self::UuidNotFound => 'UUID no existe.',
            self::NotPrimarySector => 'UUID no corresponde a un CFDI del sector primario.',
            self::InvalidReason => 'Motivo de cancelación inválido.',
            self::InvalidReplacementFolio => 'Folio de sustitución inválido.',
            self::ReplacementFolioNotRequired => 'Folio de sustitución no requerido.',
            self::RequestDateAfterDeclaration => 'La fecha de solicitud de cancelación es mayor a la fecha de declaración.',
            self::GlobalInvoiceDateLimit => 'La fecha de solicitud rebasa el límite para una factura global.',
            self::InvalidRelation => 'Relación no válida o inexistente.',
            self::NotCancellable => 'El UUID contiene CFDI relacionados.',
            self::InvalidUser => 'Usuario no válido.',
            self::MalformedXml => 'XML mal formado.',
            self::MalformedSeal => 'Sello mal formado.',
            self::RevokedOrExpiredCertificate => 'Certificado revocado o caduco.',
            self::InvalidCertificate => 'Certificado inválido.',
            self::InvalidFolioPattern => 'Patrón de folio inválido.',
            self::FielInsteadOfCsd => 'Se está usando un certificado tipo FIEL y no de CSD.',
            self::InvalidReasonKey => 'Clave de motivo de cancelación no válida.',
            self::UuidNotRelatedToReason => 'UUID no relacionado de acuerdo a la clave de motivo de cancelación.',
            self::RelationNotValid => 'Relación no válida.',
            self::DoubleBase64Encoding => 'El certificado o la llave se enviaron doblemente codificados en base64.',
            self::SatUnreachable => 'No se pudo conectar al SAT.',
            self::CertificateError => 'Error con el certificado al cancelar.',
            self::AlreadyRequested => 'Ya existe una solicitud previa para este UUID.',
            self::TooManyAttempts => 'Se excedió el límite de 5 peticiones de cancelación.',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::RequestAccepted => 'Confirma la cancelación definitiva con get_sat_status(): el 201 solo confirma que la petición se realizó.',
            self::PreviouslyCancelled => 'No vuelvas a enviar la petición: consulta el estatus ante el SAT con get_sat_status().',
            self::NotFoundOrIssuerMismatch => 'Compara el taxpayer_id con el atributo Rfc del nodo Emisor del CFDI.',
            self::NotApplicable => 'El comprobante no admite cancelación por su tipo o por su antigüedad.',
            self::UuidNotFound => 'En DEMO espera de 2 a 5 minutos después de timbrar; en producción el SAT puede no tenerlo registrado todavía. Revisa también que el UUID y los datos correspondan al mismo comprobante.',
            self::NotPrimarySector => 'El comprobante no pertenece al sector primario.',
            self::InvalidReason => 'Usa una clave del catálogo del SAT (01, 02, 03 o 04) y revisa la relación entre comprobantes.',
            self::InvalidReplacementFolio => 'El motivo 01 exige un FolioSustitucion válido, existente y vigente.',
            self::ReplacementFolioNotRequired => 'El FolioSustitucion solo se admite con el motivo 01.',
            self::RequestDateAfterDeclaration => 'La cancelación procede a más tardar el 31 de enero del año siguiente al de expedición (regla 2.7.1.47 de la RMF).',
            self::GlobalInvoiceDateLimit => 'Revisa la fecha de la factura global asociada.',
            self::InvalidRelation => 'Revisa los CFDI relacionados antes de cancelar.',
            self::NotCancellable => 'Revisa las relaciones del comprobante; la relación dura unos 30 minutos.',
            self::InvalidUser => 'Verifica el usuario del panel de Finkok y que la URL corresponda al ambiente de las credenciales.',
            self::MalformedXml => 'Revisa las cabeceras y los nodos del XML; puedes validarlo en https://validador.finkok.com',
            self::MalformedSeal => 'Es una intermitencia del SAT: reintenta la solicitud unos minutos más tarde.',
            self::RevokedOrExpiredCertificate => 'Usa un CSD vigente. No tiene que ser el mismo con el que se timbró el CFDI.',
            self::InvalidCertificate => 'Los certificados emitidos entre el 03-05-2023 y el 24-05-2023 presentaron errores: usa otro.',
            self::InvalidFolioPattern => 'El UUID no corresponde a un folio fiscal o viene vacío.',
            self::FielInsteadOfCsd => 'Cancela con el CSD del emisor, no con la FIEL.',
            self::InvalidReasonKey => 'Las claves válidas son 01, 02, 03 y 04.',
            self::UuidNotRelatedToReason => 'Relaciona los comprobantes conforme a la clave de motivo que estás usando.',
            self::RelationNotValid => 'Revisa la relación entre los comprobantes.',
            self::DoubleBase64Encoding => 'Codifica el .cer y el .key una sola vez.',
            self::SatUnreachable => 'Intermitencia del SAT: reintenta la cancelación más tarde.',
            self::CertificateError => 'Verifica que el certificado esté completo y en base64 con sus encabezados, y que la llave se haya cifrado en DES3 con la contraseña del panel.',
            self::AlreadyRequested => 'Consulta el estatus con get_sat_status() y no mandes otra petición: hay que esperar 72 horas.',
            self::TooManyAttempts => 'Ya no es posible cancelar usando los servicios de Finkok: solo queda el portal del SAT.',
        };
    }

    /**
     * 	rue cuando la petición fue recibida, aunque no confirme la cancelación
     * definitiva.
     */
    public function isRequestAccepted(): bool
    {
        return match ($this) {
            self::RequestAccepted, self::PreviouslyCancelled, self::AlreadyRequested => true,
            default => false,
        };
    }

    /**
     * 	rue cuando el código rechaza la cancelación de ese UUID (rango 203-212).
     */
    public function isRejection(): bool
    {
        return match ($this) {
            self::NotFoundOrIssuerMismatch,
            self::NotApplicable,
            self::UuidNotFound,
            self::NotPrimarySector,
            self::InvalidReason,
            self::InvalidReplacementFolio,
            self::ReplacementFolioNotRequired,
            self::RequestDateAfterDeclaration,
            self::GlobalInvoiceDateLimit,
            self::InvalidRelation,
            self::NotCancellable => true,
            default => false,
        };
    }

    /**
     * 	rue cuando el UUID no existe o no corresponde al emisor.
     */
    public function isNotFound(): bool
    {
        return match ($this) {
            self::NotFoundOrIssuerMismatch, self::UuidNotFound => true,
            default => false,
        };
    }

    /**
     * 	rue cuando el código es una incidencia transitoria que conviene
     * reintentar más tarde.
     */
    public function isTransient(): bool
    {
        return match ($this) {
            self::UuidNotFound, self::MalformedSeal, self::SatUnreachable, self::AlreadyRequested => true,
            default => false,
        };
    }

    /**
     * 	rue cuando la incidencia se resuelve desde el panel de Finkok, con otro
     * certificado o contactando a soporte.
     */
    public function requiresManualAction(): bool
    {
        return match ($this) {
            self::RevokedOrExpiredCertificate,
            self::InvalidCertificate,
            self::FielInsteadOfCsd,
            self::CertificateError,
            self::TooManyAttempts,
            self::NotCancellable,
            self::RequestDateAfterDeclaration,
            self::GlobalInvoiceDateLimit => true,
            default => false,
        };
    }

    /**
     * 	rue cuando el fallo es de credenciales o de ambiente.
     */
    public function isCredentialError(): bool
    {
        return self::InvalidUser === $this;
    }

    /**
     * 	rue cuando el fallo es de codificación del CSD (base64 o cifrado de la
     * llave) y no del comprobante.
     */
    public function isConfigurationError(): bool
    {
        return match ($this) {
            self::DoubleBase64Encoding, self::CertificateError => true,
            default => false,
        };
    }
}
