<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

/**
 * Catálogo de códigos de incidencia documentados por Finkok.
 *
 * Finkok devuelve los errores de negocio dentro del nodo `<Incidencias>` de una
 * respuesta SOAP correcta (no como SOAP Fault). Este enum traduce cada
 * `CodigoError` a su descripción oficial y a una pista de solución, para que la
 * aplicación pueda reaccionar de forma programática en lugar de comparar textos.
 *
 * ```php
 * foreach ($receipt->getIncidences() as $incidencia) {
 *     $codigo = ErrorCode::tryFrom((string) $incidencia->code);
 *
 *     if ($codigo?->isAlreadyStamped()) {
 *         // el CFDI ya estaba timbrado: recuperar el XML con stamped()
 *     }
 * }
 * ```
 */
enum ErrorCode: string
{
    case InvalidCredentials = '300';
    case MalformedXml = '301';
    case SealDoesNotMatchIssuer = '303';
    case RevokedOrExpiredCertificate = '304';
    case IssueDateOutsideCertificateValidity = '305';
    case NotACsdCertificate = '306';
    case AlreadyStamped = '307';
    case CertificateNotIssuedBySat = '308';
    case IssueDateOutOfRange = '401';
    case IssuerRfcNotRegisteredInTaxRegime = '402';
    case MissingPreviousStamp = '603';
    case IssuerSuspended = '701';
    case IssuerNotRegisteredInFinkok = '702';
    case AccountSuspended = '703';
    case InvalidXmlStructure = '705';
    case ExistingStamp = '707';
    case SatSealCouldNotBeCreated = '709';
    case CertificateNumberMismatch = '712';
    case StampsExhausted = '718';
    case IssuerRfcDoesNotMatchCertificate = '719';
    case IssuerHasNoActiveCertificate = '720';
    case SchemaLocationOrNamespaceError = '738';
    case ManifestSignatureError = '740';
    case CfdiDigestMismatch = 'CFDI40102';

    /**
     * Descripción oficial de Finkok/SAT para el código.
     */
    public function description(): string
    {
        return match ($this) {
            self::InvalidCredentials => 'El usuario o contraseña son inválidos.',
            self::MalformedXml => 'XML mal formado.',
            self::SealDoesNotMatchIssuer => 'Sello no corresponde a emisor.',
            self::RevokedOrExpiredCertificate => 'Certificado revocado o caduco.',
            self::IssueDateOutsideCertificateValidity => 'La fecha de emisión no está dentro de la vigencia del CSD del emisor.',
            self::NotACsdCertificate => 'El certificado no es de tipo CSD.',
            self::AlreadyStamped => 'El CFDI contiene un timbre previo.',
            self::CertificateNotIssuedBySat => 'Certificado no expedido por el SAT.',
            self::IssueDateOutOfRange => 'Fecha y hora de generación fuera de rango.',
            self::IssuerRfcNotRegisteredInTaxRegime => 'RFC del emisor no se encuentra en el régimen de contribuyentes.',
            self::MissingPreviousStamp => 'El CFDI no contiene un timbre previo (método stamped).',
            self::IssuerSuspended => 'Cliente o RFC emisor suspendido.',
            self::IssuerNotRegisteredInFinkok => 'No ha registrado el RFC emisor bajo la cuenta de Finkok.',
            self::AccountSuspended => 'Cuenta suspendida.',
            self::InvalidXmlStructure => 'XML estructura inválida.',
            self::ExistingStamp => 'Timbre existente.',
            self::SatSealCouldNotBeCreated => 'SelloSat no pudo ser creado.',
            self::CertificateNumberMismatch => 'El atributo noCertificado no corresponde al certificado.',
            self::StampsExhausted => 'Timbres agotados, por favor, contacte a su proveedor.',
            self::IssuerRfcDoesNotMatchCertificate => 'RFC del Emisor no corresponde al noCertificado.',
            self::IssuerHasNoActiveCertificate => 'RFC del Emisor no tiene Certificado Activo.',
            self::SchemaLocationOrNamespaceError => 'Errores con schemaLocations, namespaces y prefijos.',
            self::ManifestSignatureError => 'Error Firma de Manifiesto.',
            self::CfdiDigestMismatch => 'El resultado de la digestión debe ser igual al resultado de la desencripción del sello.',
        };
    }

    /**
     * Acción concreta recomendada para resolver la incidencia.
     */
    public function hint(): string
    {
        return match ($this) {
            self::InvalidCredentials => 'Verifica usuario y contraseña, y confirma que estés usando la URL del mismo ambiente (demo/producción) al que pertenecen las credenciales.',
            self::MalformedXml => 'Valida el XML en https://validador.finkok.com; suele deberse a campos vacíos o a la falta de atributos obligatorios.',
            self::SealDoesNotMatchIssuer => 'El sello se generó con un archivo .key que no corresponde al emisor declarado en el XML.',
            self::RevokedOrExpiredCertificate => 'Consulta la lista de certificados del SAT y sustituye el CSD por uno vigente.',
            self::IssueDateOutsideCertificateValidity => 'Espera hasta 72 horas después de la emisión del certificado: la lista LCO del SAT tarda en actualizarse.',
            self::NotACsdCertificate => 'Estás usando el certificado de la FIEL; genera el CFDI con tu CSD (la FIEL solo aplica al servicio gratuito del SAT).',
            self::AlreadyStamped => 'El comprobante ya estaba timbrado. Finkok recupera el XML automáticamente: espera unos segundos y vuelve a leerlo, o usa stamped() para obtenerlo del historial.',
            self::CertificateNotIssuedBySat => 'En DEMO debes usar RFC y certificados del kit de pruebas, no los reales.',
            self::IssueDateOutOfRange => 'El SAT solo acepta timbrado dentro de las 72 horas posteriores a la fecha de emisión y rechaza fechas futuras. Corrige el atributo Fecha.',
            self::IssuerRfcNotRegisteredInTaxRegime => 'Revisa que el RFC del XML coincida con el del panel de Finkok, que sea CSD (no FIEL) y espera 72 horas si el certificado es nuevo.',
            self::MissingPreviousStamp => 'El UUID consultado todavía no está timbrado; primero llama a stamp() y espera a que se propague.',
            self::IssuerSuspended => 'Ingresa al panel de Finkok (demo o producción) y habilita el RFC emisor en la sección «Clientes».',
            self::IssuerNotRegisteredInFinkok => 'Registra el RFC emisor en la sección «Clientes» del panel correspondiente al ambiente.',
            self::AccountSuspended => 'Existe un adeudo. Realiza el pago y regístralo en la sección «Cobranza» del panel para reactivar la cuenta.',
            self::InvalidXmlStructure => 'Verifica los namespaces y el schemaLocation, que el XML se envíe una sola vez codificado en base64 y que el XML no esté doblemente codificado. Si son retenciones, usa la URL de retentions.wsdl.',
            self::ExistingStamp => 'El XML ya contiene un nodo TimbreFiscalDigital: genera el comprobante sin el nodo TFD antes de timbrar.',
            self::SatSealCouldNotBeCreated => 'Incidencia transitoria del ambiente DEMO por balanceo del servicio. Reintenta en unos momentos.',
            self::CertificateNumberMismatch => 'El atributo NoCertificado del XML no coincide con el número de serie del certificado utilizado.',
            self::StampsExhausted => 'El RFC emisor tiene 0 timbres asignados en el panel: asígnale timbres en la sección «Clientes».',
            self::IssuerRfcDoesNotMatchCertificate => 'sign_stamp requiere que el CSD esté cargado en el panel de Finkok para ese RFC.',
            self::IssuerHasNoActiveCertificate => 'Carga los CSD del emisor en el panel de Finkok o usa el método Edit del Web Service de registro de clientes.',
            self::SchemaLocationOrNamespaceError => 'Revisa que el atributo xsi:schemaLocation y los namespaces del CFDI estén completos y correctos.',
            self::ManifestSignatureError => 'El RFC emisor debe firmar el manifiesto de conformidad (regla 2.7.2.1, fracción II de la RFM 2022).',
            self::CfdiDigestMismatch => 'La cadena original o el sello están mal generados: revisa el XSLT del complemento, los espacios, los saltos de línea, que la cadena esté en UTF-8 y que el algoritmo sea SHA-256.',
        };
    }

    /**
     * Indica si la incidencia puede desaparecer reintentando la misma petición.
     */
    public function isTransient(): bool
    {
        return match ($this) {
            self::SatSealCouldNotBeCreated, self::AlreadyStamped => true,
            default => false,
        };
    }

    /**
     * Indica si la incidencia se resuelve desde el panel de Finkok o con trámites
     * ante el SAT, es decir, no se corrige cambiando el código de la aplicación.
     */
    public function requiresManualAction(): bool
    {
        return match ($this) {
            self::IssuerSuspended,
            self::IssuerNotRegisteredInFinkok,
            self::AccountSuspended,
            self::StampsExhausted,
            self::IssuerHasNoActiveCertificate,
            self::ManifestSignatureError,
            self::RevokedOrExpiredCertificate => true,
            default => false,
        };
    }

    /**
     * Indica que el comprobante ya lleva un timbre y no debe volver a timbrarse.
     */
    public function isAlreadyStamped(): bool
    {
        return match ($this) {
            self::AlreadyStamped, self::ExistingStamp => true,
            default => false,
        };
    }
}
