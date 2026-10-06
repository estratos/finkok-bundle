<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Hydrator;

use Estratos\FinkokBundle\Model\QueryPendingResult;
use Estratos\FinkokBundle\Xml\DomReader;

/**
 * Construye un {@see QueryPendingResult} desde el resultado de `query_pending`.
 */
final class QueryPendingResultHydrator
{
    private function __construct()
    {
    }

    public static function hydrate(?\DOMElement $result): QueryPendingResult
    {
        if (null === $result) {
            return new QueryPendingResult();
        }

        $xmlNode = DomReader::firstElement($result, 'xml');

        return new QueryPendingResult(
            status: DomReader::textOf($result, 'status'),
            xml: null === $xmlNode ? null : DomReader::xmlText($xmlNode),
            uuid: DomReader::textOf($result, 'uuid'),
            uuidStatus: DomReader::textOf($result, 'uuid_status'),
            nextAttempt: DomReader::textOf($result, 'next_attempt'),
            attempts: DomReader::textOf($result, 'attempts'),
            error: DomReader::textOf($result, 'error'),
            date: DomReader::textOf($result, 'date'),
            incidences: IncidenceHydrator::fromResultNode($result),
        );
    }
}
