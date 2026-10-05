<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Xml;

use Finkok\CfdiBundle\Exception\ValidationException;

/**
 * Validación local del CFDI antes de enviarlo a Finkok.
 *
 * Finkok devuelve incidencias difíciles de diagnosticar cuando el XML llega
 * malformado (301 «XML mal formado», 705 «XML estructura inválida») o cuando
 * excede el límite de 1 MB. Estas comprobaciones se hacen en local, son
 * instantáneas, no consumen timbres y producen mensajes claros.
 *
 * Deliberadamente **no** se rechaza un XML que ya contiene
 * `tfd:TimbreFiscalDigital`: enviarlo a `stamp()` es el mecanismo documentado
 * por Finkok para recuperar el XML original mediante la incidencia 307.
 */
final class CfdiPreflightValidator
{
    public function __construct(
        private readonly bool $enabled = true,
        /**
         * Cuando está activo se exige que el comprobante traiga el atributo
         * `Sello`. Un CFDI sin sello provoca la incidencia CFDI40102 en Finkok.
         */
        private readonly bool $requireSignature = true,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Valida el documento y devuelve un resumen de advertencias no bloqueantes.
     *
     * @return list<string> advertencias (por ejemplo, que el CFDI ya esté timbrado)
     *
     * @throws ValidationException si el CFDI no puede enviarse a Finkok
     */
    public function validate(CfdiDocument $cfdi): array
    {
        if (!$this->enabled) {
            return [];
        }

        if ('' === trim($cfdi->content())) {
            throw ValidationException::forEmptyXml();
        }

        if ($cfdi->exceedsSizeLimit()) {
            throw ValidationException::forOversizedXml($cfdi->sizeInBytes(), CfdiDocument::MAX_SIZE_BYTES);
        }

        if (!$cfdi->isWellFormed()) {
            throw ValidationException::forMalformedXml(
                'libxml reportó errores de estructura; verifica el XML en https://validador.finkok.com',
            );
        }

        if (!$cfdi->isCfdi()) {
            throw ValidationException::forUnexpectedRootElement($cfdi->rootName(), 'Comprobante');
        }

        if ($this->requireSignature && !$cfdi->hasSignature()) {
            throw new ValidationException(
                'El CFDI no tiene el atributo Sello en el nodo Comprobante. '
                .'Finkok respondería con la incidencia CFDI40102 («El resultado de la digestión debe ser igual '
                .'al resultado de la desencripción del sello»). Firma el comprobante antes de timbrarlo.',
            );
        }

        $warnings = [];

        if ($cfdi->hasStamp()) {
            $warnings[] = sprintf(
                'El CFDI ya contiene un TimbreFiscalDigital (UUID %s). stamp() devolverá la incidencia 307 '
                .'y Finkok recuperará el XML original; para leerlo del historial usa stamped().',
                $cfdi->uuid() ?? 'desconocido',
            );
        }

        if (null === $cfdi->version()) {
            $warnings[] = 'El CFDI no declara el atributo Version; verifica que sea 3.3 o 4.0.';
        }

        return $warnings;
    }
}
