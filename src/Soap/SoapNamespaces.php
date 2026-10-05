<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Soap;

/**
 * Namespaces XML usados por los Web Services de Finkok.
 *
 * Los WSDL son document/literal con `elementFormDefault="qualified"`, de modo
 * que **cada** elemento debe declararse en el namespace que le corresponde según
 * el esquema donde fue declarado. Confundirlos provoca respuestas vacías o la
 * incidencia 738 («Errores con schemaLocations, namespaces y prefijos»).
 *
 * Ejemplo del método `cancel`, donde conviven dos namespaces:
 *
 * ```xml
 * <tns:cancel>
 *   <tns:UUIDS>                                  <!-- declarado en el esquema de cancel -->
 *     <s0:UUID UUID="…" Motivo="01"/>            <!-- declarado en apps.services.soap.core.views -->
 *   </tns:UUIDS>
 * </tns:cancel>
 * ```
 */
final class SoapNamespaces
{
    /** Envelope SOAP 1.1. */
    public const ENVELOPE = 'http://schemas.xmlsoap.org/soap/envelope/';

    /** Instancia de esquema XML, para atributos como `xsi:nil`. */
    public const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * Namespace de los tipos compartidos por todos los servicios de Finkok
     * (`AcuseRecepcionCFDI`, `UUIDArray`, `UUID_AR`, `Folio`, `Incidencia`, …).
     */
    public const VIEWS = 'apps.services.soap.core.views';

    private function __construct()
    {
    }
}
