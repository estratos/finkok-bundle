<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

use Estratos\FinkokBundle\Model\Concerns\AssertsSuccess;

/**
 * Acuse de recepción devuelto por el Web Service de timbrado.
 *
 * Corresponde al complexType `apps.services.soap.core.views:AcuseRecepcionCFDI`,
 * que es la respuesta de `stamp`, `quick_stamp`, `stamped` y `sign_stamp`.
 *
 * Regla de oro documentada por Finkok: un comprobante puede haberse timbrado
 * correctamente y aun así venir acompañado de incidencias, por lo que la
 * aplicación debe decidir con `CodEstatus` y con la presencia del UUID, no con
 * las incidencias.
 */
final class StampReceipt implements FinkokResultInterface
{
    use AssertsSuccess;

    /** `CodEstatus` de un timbrado nuevo y correcto. */
    public const STATUS_STAMPED = 'Comprobante timbrado satisfactoriamente';

    /** `CodEstatus` cuando el CFDI ya estaba timbrado (incidencia 307) y Finkok recupera el XML. */
    public const STATUS_PREVIOUSLY_STAMPED = 'Comprobante timbrado previamente';

    public function __construct(
        /** UUID (Folio Fiscal) del CFDI timbrado. */
        public readonly ?string $uuid = null,
        /** XML timbrado, con el nodo `tfd:TimbreFiscalDigital` ya insertado. */
        public readonly ?string $xml = null,
        /** `CodEstatus` textual devuelto por Finkok. */
        public readonly ?string $status = null,
        /** `Fecha` del timbrado. */
        public readonly ?string $date = null,
        /** `SatSeal` (sello del SAT). */
        public readonly ?string $satSeal = null,
        /** `NoCertificadoSAT` del PAC. */
        public readonly ?string $satCertificateNumber = null,
        /** `faultcode` cuando Finkok reporta el fallo como dato. */
        public readonly ?string $faultCode = null,
        /** `faultstring` cuando Finkok reporta el fallo como dato. */
        public readonly ?string $faultString = null,
        public readonly IncidenceCollection $incidences = new IncidenceCollection(),
        /** Cuerpo SOAP crudo, útil para depurar incidencias 705/738. */
        public readonly ?string $rawResponse = null,
    ) {
    }

    /**
     * `true` cuando el comprobante quedó timbrado, sin importar si el timbre es
     * nuevo o si ya existía previamente.
     */
    public function isSuccess(): bool
    {
        if ($this->hasFault()) {
            return false;
        }

        return null !== $this->uuid && '' !== trim($this->uuid);
    }

    /**
     * `true` cuando la respuesta trae un `faultcode`/`faultstring` como dato (no
     * como SOAP Fault de protocolo).
     */
    public function hasFault(): bool
    {
        return (null !== $this->faultCode && '' !== trim($this->faultCode))
            || (null !== $this->faultString && '' !== trim($this->faultString));
    }

    /**
     * `true` cuando el timbre se generó en esta llamada.
     */
    public function isFreshStamp(): bool
    {
        return $this->statusContains(self::STATUS_STAMPED);
    }

    /**
     * `true` cuando el CFDI ya estaba timbrado (incidencia 307) y Finkok
     * devolvió —o está por devolver— el XML original.
     */
    public function isPreviouslyStamped(): bool
    {
        return $this->statusContains(self::STATUS_PREVIOUSLY_STAMPED)
            || $this->hasErrorCode(ErrorCode::AlreadyStamped);
    }

    /**
     * `true` cuando la respuesta incluye el XML timbrado.
     *
     * En un 307 el XML puede tardar unos segundos en estar disponible: cuando
     * este método devuelve `false` conviene reintentar con `stamped()`.
     */
    public function hasStampedXml(): bool
    {
        return null !== $this->xml && '' !== trim($this->xml);
    }

    /**
     * XML timbrado, o `null` si aún no está disponible.
     */
    public function getStampedXml(): ?string
    {
        return $this->hasStampedXml() ? $this->xml : null;
    }

    /**
     * Extrae el UUID directamente del XML timbrado, como verificación cruzada
     * contra el UUID reportado en el acuse.
     */
    public function uuidFromXml(): ?string
    {
        if (!$this->hasStampedXml()) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            if (!@$document->loadXML((string) $this->xml)) {
                return null;
            }

            foreach ($document->getElementsByTagNameNS('http://www.sat.gob.mx/TimbreFiscalDigital', 'TimbreFiscalDigital') as $node) {
                if ($node instanceof \DOMElement) {
                    $uuid = $node->getAttribute('UUID');

                    return '' !== $uuid ? $uuid : null;
                }
            }

            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function getStatusCode(): ?string
    {
        return $this->status;
    }

    public function getIncidences(): IncidenceCollection
    {
        return $this->incidences;
    }

    /**
     * El UUID es la prueba fiscal del timbrado; casi siempre es lo único que la
     * aplicación necesita persistir.
     */
    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    private function statusContains(string $needle): bool
    {
        if (null === $this->status) {
            return false;
        }

        return str_contains(strtolower(trim($this->status)), strtolower($needle));
    }
}
