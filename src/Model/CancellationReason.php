<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model;

/**
 * Motivos de cancelación del CFDI 4.0 (atributo `Motivo` del nodo `UUID`).
 *
 * Reglas del SAT que este bundle valida localmente:
 *  - el motivo `01` exige indicar el UUID que sustituye al comprobante cancelado
 *    (`FolioSustitucion`);
 *  - los motivos `02`, `03` y `04` no permiten `FolioSustitucion`.
 */
enum CancellationReason: string
{
    /** Comprobante emitido con errores con relación. */
    case ErrorsWithRelation = '01';

    /** Comprobante emitido con errores sin relación. */
    case ErrorsWithoutRelation = '02';

    /** No se llevó a cabo la operación. */
    case OperationNotCarriedOut = '03';

    /** Operación nominativa relacionada en una factura global. */
    case NominativeGlobalInvoice = '04';

    public function description(): string
    {
        return match ($this) {
            self::ErrorsWithRelation => 'Comprobante emitido con errores con relación.',
            self::ErrorsWithoutRelation => 'Comprobante emitido con errores sin relación.',
            self::OperationNotCarriedOut => 'No se llevó a cabo la operación.',
            self::NominativeGlobalInvoice => 'Operación nominativa relacionada en una factura global.',
        };
    }

    /**
     * Indica si el motivo obliga a informar el UUID de sustitución.
     */
    public function requiresReplacementFolio(): bool
    {
        return self::ErrorsWithRelation === $this;
    }

    /**
     * Indica si el motivo prohíbe informar el UUID de sustitución.
     */
    public function forbidsReplacementFolio(): bool
    {
        return self::ErrorsWithRelation !== $this;
    }

    /**
     * Descripción combinada lista para mostrar en catálogos de interfaz.
     */
    public function label(): string
    {
        return $this->value.' - '.$this->description();
    }
}
