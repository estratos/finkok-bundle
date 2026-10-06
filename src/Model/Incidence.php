<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

/**
 * Una incidencia devuelta por Finkok dentro de `<Incidencias>`.
 *
 * Corresponde al complexType `apps.services.soap.core.views:Incidencia`.
 *
 * Importante: la documentación de Finkok advierte que un comprobante puede
 * haberse timbrado correctamente y aun así regresar incidencias. Por eso la
 * lógica de integración debe basarse en `CodEstatus` y en el UUID, nunca en la
 * sola presencia de incidencias.
 */
final class Incidence
{
    public function __construct(
        /** IdIncidencia */
        public readonly ?string $id = null,
        /** CodigoError */
        public readonly ?string $code = null,
        /** MensajeIncidencia */
        public readonly ?string $message = null,
        /** ExtraInfo */
        public readonly ?string $extraInfo = null,
        /** Uuid */
        public readonly ?string $uuid = null,
        /** RfcEmisor */
        public readonly ?string $emitterRfc = null,
        /** WorkProcessId */
        public readonly ?string $workProcessId = null,
        /** NoCertificadoPac */
        public readonly ?string $pacCertificateNumber = null,
        /** FechaRegistro */
        public readonly ?string $registeredAt = null,
    ) {
    }

    /**
     * Código de error tipificado, o `null` si Finkok devolvió un código no
     * documentado en este bundle.
     */
    public function errorCode(): ?ErrorCode
    {
        if (null === $this->code) {
            return null;
        }

        return ErrorCode::tryFrom(strtoupper(trim($this->code)));
    }

    /**
     * Pista de solución asociada al código, si se conoce.
     */
    public function hint(): ?string
    {
        return $this->errorCode()?->hint();
    }

    /**
     * Representación amigable con la información más útil, sin `guzzle` ni
     * `print_r`, pensada para logs y pantallas de error.
     */
    public function describe(): string
    {
        $parts = [sprintf('[%s] %s', $this->code ?? 'sin código', $this->message ?? 'sin mensaje')];

        if (null !== $this->uuid && '' !== $this->uuid) {
            $parts[] = 'UUID: '.$this->uuid;
        }

        if (null !== $this->hint()) {
            $parts[] = 'Sugerencia: '.$this->hint();
        }

        return implode(' | ', $parts);
    }

    public function __toString(): string
    {
        return $this->describe();
    }
}
