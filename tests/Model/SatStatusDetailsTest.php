<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Model;

use Estratos\FinkokBundle\Model\SatStatusDetails;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Traducción de `ValidacionEFOS` (el RFC emisor contra el listado de EFOS del SAT).
 *
 * La tabla que se prueba es la del SAT, no la que «parecía»: los códigos que listan al emisor son
 * `100`, `101` y `104`; `200`/`201` son justo lo contrario («no listado») y `102`/`103` son el caso
 * «a cuenta de terceros». Ver `docs/errores.md` §6.
 */
final class SatStatusDetailsTest extends TestCase
{
    #[DataProvider('codigosDeValidacionEfos')]
    public function testTraduceElCodigoDeValidacionEfos(
        ?string $codigo,
        bool $emisorListado,
        bool $terceroListado,
    ): void {
        $detalle = new SatStatusDetails(efosValidation: $codigo);

        self::assertSame($emisorListado, $detalle->isListedAsEfos(), 'isListedAsEfos()');
        self::assertSame($terceroListado, $detalle->hasListedThirdParty(), 'hasListedThirdParty()');
    }

    public static function codigosDeValidacionEfos(): iterable
    {
        yield '100: el emisor está listado' => ['100', true, false];
        yield '101: el emisor está listado' => ['101', true, false];
        yield '104: el emisor está listado' => ['104', true, false];
        yield '102: listado el tercero, no el emisor' => ['102', false, true];
        yield '103: listado el tercero, no el emisor' => ['103', false, true];
        yield '200: no listado' => ['200', false, false];
        yield '201: no listado' => ['201', false, false];
        yield 'sin dato: no se supone nada' => [null, false, false];
        yield 'vacío: no se supone nada' => ['', false, false];
        yield 'código no documentado: no se supone nada' => ['999', false, false];
        yield 'con espacios alrededor: se ignoran' => [' 100 ', true, false];
    }
}
