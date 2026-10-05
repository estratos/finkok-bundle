<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Model;

/**
 * Tipo de acuse que devuelve el método `get_receipt` (parámetro `type`).
 *
 * Valores documentados por Finkok: `I` para el acuse de recepción y `C` para el
 * acuse de cancelación.
 */
enum ReceiptType: string
{
    /** Acuse de recepción (`I`). */
    case Reception = 'I';

    /** Acuse de cancelación (`C`). */
    case Cancellation = 'C';

    public function description(): string
    {
        return match ($this) {
            self::Reception => 'Acuse de recepción del CFDI.',
            self::Cancellation => 'Acuse de cancelación del CFDI.',
        };
    }
}
