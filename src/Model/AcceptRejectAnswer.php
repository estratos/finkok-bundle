<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Model;

/**
 * Respuesta que el receptor da a una solicitud de cancelación, enviada a través
 * del método `accept_reject` (campo `respuesta` del complejo `UUID_AR`).
 *
 * **Nota de integración**: Finkok documenta este campo como `xs:string` sin
 * enumerar los valores válidos, y la pareja aceptar/rechazar se ha visto escrita
 * de varias formas a lo largo de las versiones del servicio. Los valores de este
 * enum son los que usa el PAC internamente (`Aceptacion` / `Rechazo`) y coinciden
 * con la nomenclatura empleada por otras librerías de integración.
 *
 * Si tu cuenta espera otra literal, todos los métodos que aceptan este enum
 * admiten también una cadena cruda, así que puedes enviar el valor exacto sin
 * modificar el bundle:
 *
 * ```php
 * $cancel->acceptReject(['A1B2…' => 'Aceptar']);
 * ```
 */
enum AcceptRejectAnswer: string
{
    case Accepted = 'Aceptacion';
    case Rejected = 'Rechazo';

    /**
     * Interpreta la notación corta del SAT (`A` acepta, `R` rechaza).
     */
    public static function fromSat(string $answer): self
    {
        return match (strtoupper(trim($answer))) {
            'A', 'ACEPTACION', 'ACEPTACIÓN', 'ACEPTAR', 'ACEPTA' => self::Accepted,
            'R', 'RECHAZO', 'RECHAZAR', 'RECHAZA' => self::Rejected,
            default => throw new \InvalidArgumentException(sprintf(
                'Respuesta de cancelación desconocida "%s". Se esperaba "A" (aceptar) o "R" (rechazar).',
                $answer,
            )),
        };
    }

    public function isAccepted(): bool
    {
        return self::Accepted === $this;
    }

    public function description(): string
    {
        return match ($this) {
            self::Accepted => 'El receptor acepta la solicitud de cancelación.',
            self::Rejected => 'El receptor rechaza la solicitud de cancelación.',
        };
    }
}
