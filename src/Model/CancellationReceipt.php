<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

use Estratos\FinkokBundle\Model\Concerns\AssertsSuccess;

/**
 * Resultado del Web Service de cancelación (`Cancel`).
 *
 * Corresponde al complexType `apps.services.soap.core.views:CancelaCFDResult`.
 *
 * Importante: este tipo **no** incluye el nodo `<Incidencias>`. Los errores de
 * cancelación llegan en `CodEstatus`, por lo que `getStatusCode()` es la fuente
 * principal de diagnóstico.
 */
final class CancellationReceipt implements FinkokResultInterface
{
    use AssertsSuccess;

    /**
     * `CodEstatus` que devuelve el servicio de cancelación cuando las
     * credenciales no son válidas.
     *
     * A diferencia del Web Service de timbrado —que reporta este caso como la
     * incidencia 300 «El usuario o contraseña son inválidos»—, el de cancelación
     * responde con este texto en inglés y sin nodo `<Incidencias>`. Por eso
     * `cancellationStatusCode()` devuelve `null` en ese caso y conviene usar
     * {@see self::isCredentialError()} para distinguirlo.
     */
    public const STATUS_INVALID_CREDENTIALS = 'Invalid Username or Password';

    /**
     * @param list<CancellationFolio> $folios
     */
    public function __construct(
        public readonly array $folios = [],
        /** `Acuse`: acuse de cancelación emitido por el SAT. */
        public readonly ?string $acknowledgment = null,
        /** `Fecha` de la solicitud. */
        public readonly ?string $date = null,
        /** `RfcEmisor` del comprobante cancelado. */
        public readonly ?string $emitterRfc = null,
        /** `CodEstatus` de la operación. */
        public readonly ?string $status = null,
        public readonly IncidenceCollection $incidences = new IncidenceCollection(),
        /** Cuerpo SOAP crudo, útil para depurar. */
        public readonly ?string $rawResponse = null,
    ) {
    }

    /**
     * `true` cuando el SAT aceptó la cancelación, ya sea de forma definitiva o
     * dejándola en proceso por falta de aceptación del receptor.
     *
     * Recuerda la advertencia de Finkok: el código 201 confirma que la *petición*
     * se realizó correctamente, no que el CFDI ya esté cancelado. Para tener
     * certeza hay que consultar `get_sat_status`.
     */
    public function isSuccess(): bool
    {
        if ($this->hasAcknowledgment()) {
            return true;
        }

        foreach ($this->folios as $folio) {
            if ($folio->isCancellationAcknowledged()) {
                return true;
            }
        }

        return true === $this->cancellationStatusCode()?->isRequestAccepted();
    }

    /**
     * Código de `CodEstatus` tipificado, o `null` si Finkok devolvió un valor no
     * documentado.
     */
    public function cancellationStatusCode(): ?CancellationStatusCode
    {
        if (null === $this->status) {
            return null;
        }

        return CancellationStatusCode::tryFrom(trim($this->status));
    }

    /**
     * Reconoce el código 300 («Usuario no válido») que este Web Service usa en
     * `CodEstatus`, además de los textos que ya detecta la implementación común.
     */
    protected function isCredentialStatusCode(): bool
    {
        return CancellationStatusCode::InvalidUser === $this->cancellationStatusCode();
    }

    /**
     * UUIDs a los que Finkok aceptó la petición (201, 202 u 798).
     *
     * Ojo: no implica cancelación definitiva. Para eso usa
     * {@see self::cancelledUuids()} o confirma con `get_sat_status()`.
     *
     * @return list<string>
     */
    public function acceptedUuids(): array
    {
        return array_values(array_filter(array_map(
            static fn (CancellationFolio $folio): ?string => $folio->isRequestAccepted() ? $folio->uuid : null,
            $this->folios,
        )));
    }

    /**
     * `true` cuando el SAT ya emitió el acuse de cancelación.
     */
    public function hasAcknowledgment(): bool
    {
        return null !== $this->acknowledgment && '' !== trim($this->acknowledgment);
    }

    /**
     * `true` cuando algún folio quedó en proceso, normalmente a la espera de la
     * aceptación del receptor.
     *
     * El «en proceso» no viene en el código de la petición sino en el texto
     * `EstatusCancelacion`, así que se consulta en cada folio.
     */
    public function isInProcess(): bool
    {
        foreach ($this->folios as $folio) {
            if ($folio->isInProcess()) {
                return true;
            }

            if ($folio->isCancelled()) {
                return false;
            }
        }

        return false;
    }

    /**
     * UUIDs cancelados de forma definitiva.
     *
     * @return list<string>
     */
    public function cancelledUuids(): array
    {
        return array_values(array_filter(array_map(
            static fn (CancellationFolio $folio): ?string => $folio->isCancelled() ? $folio->uuid : null,
            $this->folios,
        )));
    }

    /**
     * UUIDs cuya solicitud quedó en proceso.
     *
     * @return list<string>
     */
    public function pendingUuids(): array
    {
        return array_values(array_filter(array_map(
            static fn (CancellationFolio $folio): ?string => $folio->isInProcess() ? $folio->uuid : null,
            $this->folios,
        )));
    }

    /**
     * UUIDs que el SAT rechazó o marcó como no cancelables.
     *
     * @return list<string>
     */
    public function rejectedUuids(): array
    {
        return array_values(array_filter(array_map(
            static fn (CancellationFolio $folio): ?string => $folio->isRejected() ? $folio->uuid : null,
            $this->folios,
        )));
    }

    public function getFolio(string $uuid): ?CancellationFolio
    {
        foreach ($this->folios as $folio) {
            if (null !== $folio->uuid && 0 === strcasecmp(trim($folio->uuid), trim($uuid))) {
                return $folio;
            }
        }

        return null;
    }

    public function getStatusCode(): ?string
    {
        return $this->status;
    }

    public function getIncidences(): IncidenceCollection
    {
        return $this->incidences;
    }
}
