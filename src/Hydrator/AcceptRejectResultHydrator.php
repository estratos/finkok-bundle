<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Hydrator;

use Finkok\CfdiBundle\Model\AcceptRejectEntry;
use Finkok\CfdiBundle\Model\AcceptRejectResult;
use Finkok\CfdiBundle\Xml\DomReader;

/**
 * Construye un {@see AcceptRejectResult} desde el resultado de `accept_reject`.
 *
 * Estructura esperada (`AcceptRejectResult`):
 *
 * ```xml
 * <accept_rejectResult>
 *   <aceptacion>
 *     <Acepta><status>201</status><uuid>…</uuid></Acepta>
 *   </aceptacion>
 *   <rechazo>
 *     <Rechaza><status>202</status><uuid>…</uuid></Rechaza>
 *   </rechazo>
 *   <error/>
 * </accept_rejectResult>
 * ```
 */
final class AcceptRejectResultHydrator
{
    private function __construct()
    {
    }

    public static function hydrate(?\DOMElement $result, ?string $rawResponse = null): AcceptRejectResult
    {
        if (null === $result) {
            return new AcceptRejectResult(rawResponse: $rawResponse);
        }

        return new AcceptRejectResult(
            accepted: self::hydrateEntries($result, 'aceptacion', 'Acepta', true),
            rejected: self::hydrateEntries($result, 'rechazo', 'Rechaza', false),
            error: DomReader::textOf($result, 'error'),
            incidences: IncidenceHydrator::fromResultNode($result),
            rawResponse: $rawResponse,
        );
    }

    /**
     * @return list<AcceptRejectEntry>
     */
    private static function hydrateEntries(
        \DOMElement $result,
        string $containerName,
        string $entryName,
        bool $accepted,
    ): array {
        $container = DomReader::firstElement($result, $containerName);
        $nodes = null === $container ? [] : DomReader::elements($container, $entryName);

        if ([] === $nodes) {
            // Variante sin contenedor: las entradas cuelgan directamente del resultado.
            $nodes = DomReader::elements($result, $entryName);
        }

        $entries = [];
        foreach ($nodes as $node) {
            $entries[] = new AcceptRejectEntry(
                uuid: DomReader::textOf($node, 'uuid') ?? DomReader::textOf($node, 'UUID'),
                status: DomReader::textOf($node, 'status'),
                accepted: $accepted,
            );
        }

        return $entries;
    }
}
