<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Model;

use Finkok\CfdiBundle\Exception\ValidationException;

/**
 * Un UUID a cancelar con su motivo y, en su caso, su folio de sustitución.
 *
 * Corresponde al complexType `apps.services.soap.core.views:UUID`, que se envía
 * como valor del atributo `UUID` más los atributos opcionales `Motivo` y
 * `FolioSustitucion`.
 *
 * ```php
 * $uuid = new CancellationUuid(
 *     'A1B2C3D4-1111-2222-3333-444455556666',
 *     CancellationReason::ErrorsWithRelation,
 *     'B2C3D4E5-1111-2222-3333-444455556666',
 * );
 * ```
 */
final class CancellationUuid
{
    /**
     * Formato del Folio Fiscal: 8-4-4-4-12 caracteres hexadecimales.
     */
    private const UUID_PATTERN = '/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/';

    public function __construct(
        public readonly string $uuid,
        public readonly ?CancellationReason $reason = null,
        public readonly ?string $replacementFolio = null,
    ) {
        if ('' === trim($uuid)) {
            throw ValidationException::forEmptyArgument('uuid');
        }

        if (null !== $reason && $reason->requiresReplacementFolio()
            && (null === $replacementFolio || '' === trim($replacementFolio))) {
            throw new ValidationException(sprintf(
                'El motivo de cancelación %s (%s) exige indicar el UUID del comprobante que sustituye al cancelado.',
                $reason->value,
                $reason->description(),
            ));
        }

        if (null !== $reason && $reason->forbidsReplacementFolio()
            && null !== $replacementFolio && '' !== trim($replacementFolio)) {
            throw new ValidationException(sprintf(
                'El motivo de cancelación %s (%s) no permite indicar FolioSustitucion; '
                .'el SAT solo lo acepta con el motivo 01.',
                $reason->value,
                $reason->description(),
            ));
        }
    }

    /**
     * Construye la lista a partir de UUIDs sueltos o de valores ya construidos.
     *
     * @param iterable<string|self> $uuids
     *
     * @return list<self>
     */
    public static function normalizeList(iterable $uuids): array
    {
        $normalized = [];
        foreach ($uuids as $uuid) {
            if ($uuid instanceof self) {
                $normalized[] = $uuid;
                continue;
            }

            $normalized[] = new self((string) $uuid);
        }

        return $normalized;
    }

    /**
     * @param iterable<string|self> $uuids
     *
     * @return list<string>
     */
    public static function toUuidList(iterable $uuids): array
    {
        return array_map(
            static fn (self $uuid): string => $uuid->uuid,
            self::normalizeList($uuids),
        );
    }

    /**
     * `true` si el UUID tiene el formato de Folio Fiscal.
     *
     * Finkok acepta UUIDs con y sin guiones, por lo que esto es solo una
     * comprobación de forma y nunca un bloqueo duro.
     */
    public static function isValidUuid(string $uuid): bool
    {
        return 1 === preg_match(self::UUID_PATTERN, trim($uuid));
    }

    public function hasReason(): bool
    {
        return null !== $this->reason;
    }

    public function hasReplacementFolio(): bool
    {
        return null !== $this->replacementFolio && '' !== trim($this->replacementFolio);
    }

    public function __toString(): string
    {
        return $this->uuid;
    }
}
