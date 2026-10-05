<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Hydrator;

use Finkok\CfdiBundle\Model\SatStatusDetails;
use Finkok\CfdiBundle\Model\SatStatusResult;
use Finkok\CfdiBundle\Xml\DomReader;

/**
 * Construye un {@see SatStatusResult} desde el resultado de `get_sat_status`
 * (`AcuseSatEstatus` con su nodo `sat` de tipo `AcuseSATConsulta`).
 */
final class SatStatusResultHydrator
{
    private function __construct()
    {
    }

    public static function hydrate(?\DOMElement $result, ?string $rawResponse = null): SatStatusResult
    {
        if (null === $result) {
            return new SatStatusResult(rawResponse: $rawResponse);
        }

        $sat = DomReader::firstElement($result, 'sat');

        return new SatStatusResult(
            error: DomReader::textOf($result, 'error'),
            details: null === $sat ? null : self::hydrateDetails($sat),
            incidences: IncidenceHydrator::fromResultNode($result),
            rawResponse: $rawResponse,
        );
    }

    private static function hydrateDetails(\DOMElement $sat): SatStatusDetails
    {
        return new SatStatusDetails(
            statusCode: DomReader::textOf($sat, 'CodigoEstatus'),
            cancellable: DomReader::textOf($sat, 'EsCancelable'),
            state: DomReader::textOf($sat, 'Estado'),
            cancellationStatus: DomReader::textOf($sat, 'EstatusCancelacion'),
            efosValidation: DomReader::textOf($sat, 'ValidacionEFOS'),
            efosValidationDetails: DomReader::textOf($sat, 'DetallesValidacionEFOS'),
        );
    }
}
