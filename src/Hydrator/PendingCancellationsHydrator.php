<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Hydrator;

use Finkok\CfdiBundle\Model\PendingCancellations;
use Finkok\CfdiBundle\Xml\DomReader;

/**
 * Construye un {@see PendingCancellations} desde el resultado de `get_pending` o
 * `get_out_pending` (`CancelPendingResult`).
 *
 * El WSDL declara `<uuids>` como `stringArray` con hijos `<string>` repetidos,
 * pero algunas versiones del servicio devuelven varios `<uuids>` con el valor
 * directamente en el texto. Se soportan ambas formas.
 */
final class PendingCancellationsHydrator
{
    private function __construct()
    {
    }

    public static function hydrate(?\DOMElement $result, ?string $rawResponse = null): PendingCancellations
    {
        if (null === $result) {
            return new PendingCancellations(rawResponse: $rawResponse);
        }

        return new PendingCancellations(
            uuids: self::collectUuids($result),
            error: DomReader::textOf($result, 'error'),
            incidences: IncidenceHydrator::fromResultNode($result),
            rawResponse: $rawResponse,
        );
    }

    /**
     * @return list<string>
     */
    private static function collectUuids(\DOMElement $result): array
    {
        $containers = DomReader::elements($result, 'uuids');

        if ([] === $containers) {
            $containers = DomReader::descendants($result, 'uuids');
        }

        $uuids = [];
        foreach ($containers as $container) {
            $items = DomReader::elements($container, 'string');

            if ([] === $items) {
                $text = DomReader::text($container);
                if (null !== $text) {
                    $uuids[] = $text;
                }

                continue;
            }

            foreach ($items as $item) {
                $text = DomReader::text($item);
                if (null !== $text) {
                    $uuids[] = $text;
                }
            }
        }

        return array_values(array_unique($uuids));
    }
}
