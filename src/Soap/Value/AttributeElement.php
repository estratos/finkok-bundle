<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Soap\Value;

use Estratos\FinkokBundle\Soap\SoapValueInterface;
use Estratos\FinkokBundle\Soap\SoapWriter;

/**
 * Elemento sin contenido textual, definido únicamente por sus atributos.
 *
 * Corresponde al complexType `apps.services.soap.core.views:UUID` del Web
 * Service de cancelación, cuyos datos viajan como atributos:
 *
 * ```xml
 * <s0:UUID UUID="A1B2…" Motivo="01" FolioSustitucion="C3D4…"/>
 * ```
 *
 * Los atributos se escriben sin namespace, tal como los declara el esquema
 * (`attributeFormDefault` no está calificado).
 */
final class AttributeElement implements SoapValueInterface
{
    /** @var array<string, string> */
    private readonly array $attributes;

    /**
     * @param array<string, scalar|null> $attributes los valores `null` y las
     *                                               cadenas vacías se omiten
     */
    public function __construct(array $attributes = [])
    {
        $normalized = [];
        foreach ($attributes as $name => $value) {
            if (null === $value) {
                continue;
            }

            $text = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            if ('' === trim($text)) {
                continue;
            }

            $normalized[$name] = $text;
        }

        $this->attributes = $normalized;
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): \DOMElement
    {
        $element = $writer->element($parent, $name, $namespace);

        foreach ($this->attributes as $attributeName => $value) {
            $writer->attribute($element, $attributeName, $value);
        }

        return $element;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
