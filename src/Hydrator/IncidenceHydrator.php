<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Hydrator;

use Estratos\FinkokBundle\Model\Incidence;
use Estratos\FinkokBundle\Model\IncidenceCollection;
use Estratos\FinkokBundle\Xml\DomReader;

/**
 * Convierte el nodo `<Incidencias>` de una respuesta de Finkok en una colección
 * tipada.
 *
 * Corresponde al complexType `apps.services.soap.core.views:IncidenciaArray`,
 * cuyo único hijo repetido es `Incidencia`.
 */
final class IncidenceHydrator
{
    private function __construct()
    {
    }

    /**
     * Lee las incidencias desde el nodo `<Incidencias>`.
     */
    public static function fromIncidencesNode(?\DOMElement $incidences): IncidenceCollection
    {
        if (null === $incidences) {
            return IncidenceCollection::empty();
        }

        $incidencesList = [];
        foreach (DomReader::elements($incidences, 'Incidencia') as $node) {
            $incidencesList[] = self::hydrateIncidence($node);
        }

        return new IncidenceCollection($incidencesList);
    }

    /**
     * Busca `<Incidencias>` dentro del nodo de resultado y devuelve la colección.
     */
    public static function fromResultNode(?\DOMElement $result): IncidenceCollection
    {
        if (null === $result) {
            return IncidenceCollection::empty();
        }

        return self::fromIncidencesNode(DomReader::firstElement($result, 'Incidencias'));
    }

    private static function hydrateIncidence(\DOMElement $node): Incidence
    {
        return new Incidence(
            id: DomReader::textOf($node, 'IdIncidencia'),
            code: DomReader::textOf($node, 'CodigoError'),
            message: DomReader::textOf($node, 'MensajeIncidencia'),
            extraInfo: DomReader::textOf($node, 'ExtraInfo'),
            uuid: DomReader::textOf($node, 'Uuid'),
            emitterRfc: DomReader::textOf($node, 'RfcEmisor'),
            workProcessId: DomReader::textOf($node, 'WorkProcessId'),
            pacCertificateNumber: DomReader::textOf($node, 'NoCertificadoPac'),
            registeredAt: DomReader::textOf($node, 'FechaRegistro'),
        );
    }
}
