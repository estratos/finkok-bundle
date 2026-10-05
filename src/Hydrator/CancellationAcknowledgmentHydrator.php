<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Hydrator;

use Finkok\CfdiBundle\Model\CancellationAcknowledgment;
use Finkok\CfdiBundle\Xml\DomReader;

/**
 * Construye un {@see CancellationAcknowledgment} desde el resultado de
 * `get_receipt` (`ReceiptResult`).
 */
final class CancellationAcknowledgmentHydrator
{
    private function __construct()
    {
    }

    public static function hydrate(?\DOMElement $result, ?string $rawResponse = null): CancellationAcknowledgment
    {
        if (null === $result) {
            return new CancellationAcknowledgment(rawResponse: $rawResponse);
        }

        $receipt = DomReader::firstElement($result, 'receipt');

        return new CancellationAcknowledgment(
            uuid: DomReader::textOf($result, 'uuid') ?? DomReader::textOf($result, 'UUID'),
            success: DomReader::toBoolean(DomReader::textOf($result, 'success')),
            receipt: null === $receipt ? null : DomReader::xmlText($receipt),
            taxpayerId: DomReader::textOf($result, 'taxpayer_id'),
            error: DomReader::textOf($result, 'error'),
            date: DomReader::textOf($result, 'date'),
            incidences: IncidenceHydrator::fromResultNode($result),
            rawResponse: $rawResponse,
        );
    }
}
