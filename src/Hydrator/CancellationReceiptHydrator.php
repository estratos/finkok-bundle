<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Hydrator;

use Estratos\FinkokBundle\Model\CancellationFolio;
use Estratos\FinkokBundle\Model\CancellationReceipt;
use Estratos\FinkokBundle\Xml\DomReader;

/**
 * Construye un {@see CancellationReceipt} desde el resultado de `cancel` u
 * `out_cancel` (`CancelaCFDResult`).
 *
 * Estructura esperada:
 *
 * ```xml
 * <cancelResult>
 *   <Folios>
 *     <Folio>
 *       <UUID>…</UUID>
 *       <EstatusUUID>201</EstatusUUID>
 *       <EstatusCancelacion>Cancelado</EstatusCancelacion>
 *     </Folio>
 *   </Folios>
 *   <Acuse>…</Acuse>
 *   <Fecha>…</Fecha>
 *   <RfcEmisor>…</RfcEmisor>
 *   <CodEstatus>201</CodEstatus>
 * </cancelResult>
 * ```
 */
final class CancellationReceiptHydrator
{
    private function __construct()
    {
    }

    public static function hydrate(?\DOMElement $result, ?string $rawResponse = null): CancellationReceipt
    {
        if (null === $result) {
            return new CancellationReceipt(rawResponse: $rawResponse);
        }

        $acknowledgment = DomReader::firstElement($result, 'Acuse');

        return new CancellationReceipt(
            folios: self::hydrateFolios($result),
            acknowledgment: null === $acknowledgment ? null : DomReader::xmlText($acknowledgment),
            date: DomReader::textOf($result, 'Fecha'),
            emitterRfc: DomReader::textOf($result, 'RfcEmisor'),
            status: DomReader::textOf($result, 'CodEstatus'),
            incidences: IncidenceHydrator::fromResultNode($result),
            rawResponse: $rawResponse,
        );
    }

    /**
     * @return list<CancellationFolio>
     */
    private static function hydrateFolios(\DOMElement $result): array
    {
        // El WSDL declara FolioArray/Folio como nodos anidados, pero algunos
        // despliegues de Finkok devuelven los Folio directamente bajo el
        // resultado; se soportan ambas formas.
        $nodes = DomReader::elements($result, 'Folio');

        if ([] === $nodes) {
            $folioArray = DomReader::firstElement($result, 'Folios');
            $nodes = null === $folioArray ? [] : DomReader::elements($folioArray, 'Folio');
        }

        $folios = [];
        foreach ($nodes as $node) {
            $folios[] = new CancellationFolio(
                uuid: DomReader::textOf($node, 'UUID'),
                statusUuid: DomReader::textOf($node, 'EstatusUUID'),
                cancellationStatus: DomReader::textOf($node, 'EstatusCancelacion'),
            );
        }

        return $folios;
    }
}
