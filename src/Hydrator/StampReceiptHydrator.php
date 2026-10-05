<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Hydrator;

use Finkok\CfdiBundle\Model\StampReceipt;
use Finkok\CfdiBundle\Xml\DomReader;

/**
 * Construye un {@see StampReceipt} desde el nodo de resultado de `stamp`,
 * `quick_stamp`, `stamped` o `sign_stamp` (`AcuseRecepcionCFDI`).
 */
final class StampReceiptHydrator
{
    private function __construct()
    {
    }

    public static function hydrate(?\DOMElement $result, ?string $rawResponse = null): StampReceipt
    {
        if (null === $result) {
            return new StampReceipt(rawResponse: $rawResponse);
        }

        $xmlNode = DomReader::firstElement($result, 'xml');

        return new StampReceipt(
            uuid: DomReader::textOf($result, 'UUID'),
            xml: null === $xmlNode ? null : DomReader::xmlText($xmlNode),
            status: DomReader::textOf($result, 'CodEstatus'),
            date: DomReader::textOf($result, 'Fecha'),
            satSeal: DomReader::textOf($result, 'SatSeal'),
            satCertificateNumber: DomReader::textOf($result, 'NoCertificadoSAT'),
            faultCode: DomReader::textOf($result, 'faultcode'),
            faultString: DomReader::textOf($result, 'faultstring'),
            incidences: IncidenceHydrator::fromResultNode($result),
            rawResponse: $rawResponse,
        );
    }
}
