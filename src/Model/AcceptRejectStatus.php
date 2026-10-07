<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

/**
 * Códigos del campo `status` que Finkok devuelve por cada UUID en el método
 * `accept_reject` (elementos `Acepta` y `Rechaza`).
 *
 * Ojo: este rango (1000-1006) es exclusivo del método de aceptación/rechazo. No
 * tiene nada que ver con los códigos 201-212 del método `cancel` ni con los
 * `CodigoError` de las incidencias de timbrado.
 */
enum AcceptRejectStatus: string
{
    /** Se recibió la respuesta de la petición de forma exitosa. */
    case ResponseReceived = '1000';

    /** No existen peticiones de cancelación en espera de respuesta para el UUID. */
    case NoPendingRequests = '1001';

    /** Ya se recibió una respuesta para la petición de cancelación del UUID. */
    case AlreadyAnswered = '1002';

    /** Sello no corresponde al RFC del Receptor. */
    case SealDoesNotMatchReceiver = '1003';

    /** Existen más de una petición de cancelación para el mismo UUID. */
    case MultipleRequests = '1004';

    /** El UUID es nulo o no posee el formato correcto. */
    case InvalidUuid = '1005';

    /** Se rebasó el número máximo de solicitudes permitidas. */
    case MaxRequestsExceeded = '1006';

    public function description(): string
    {
        return match ($this) {
            self::ResponseReceived => 'Se recibió la respuesta de la petición de forma exitosa.',
            self::NoPendingRequests => 'No existen peticiones de cancelación en espera de respuesta para el UUID.',
            self::AlreadyAnswered => 'Ya se recibió una respuesta para la petición de cancelación del UUID.',
            self::SealDoesNotMatchReceiver => 'El sello no corresponde al RFC del receptor.',
            self::MultipleRequests => 'Existen más de una petición de cancelación para el mismo UUID.',
            self::InvalidUuid => 'El UUID es nulo o no posee el formato correcto.',
            self::MaxRequestsExceeded => 'Se rebasó el número máximo de solicitudes permitidas.',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::ResponseReceived => 'El SAT registró tu aceptación o rechazo; confirma el resultado final con get_sat_status().',
            self::NoPendingRequests => 'El UUID no tiene solicitudes de cancelación esperando respuesta: no hay nada que aceptar ni rechazar.',
            self::AlreadyAnswered => 'Ya se respondió antes esa solicitud; consulta el estatus con get_sat_status().',
            self::SealDoesNotMatchReceiver => 'El CSD con el que firmas la respuesta debe ser el del RFC receptor, no el del emisor.',
            self::MultipleRequests => 'El emisor envió más de una petición para el mismo UUID: revisa su flujo de cancelación.',
            self::InvalidUuid => 'Revisa el UUID enviado y su formato de folio fiscal.',
            self::MaxRequestsExceeded => 'Ya no es posible responder por este medio: contacta a soporte de Finkok.',
        };
    }

    /**
     * `true` cuando el SAT registró la respuesta de aceptación o rechazo.
     */
    public function isSuccess(): bool
    {
        return self::ResponseReceived === $this;
    }

    /**
     * `true` cuando no había nada que responder: el UUID no tiene solicitudes
     * pendientes o ya se respondió antes.
     */
    public function isNothingToAnswer(): bool
    {
        return match ($this) {
            self::NoPendingRequests, self::AlreadyAnswered => true,
            default => false,
        };
    }

    /**
     * `true` cuando el error se resuelve desde el panel de Finkok o contactando
     * a soporte.
     */
    public function requiresManualAction(): bool
    {
        return self::MaxRequestsExceeded === $this;
    }
}
