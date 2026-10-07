<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Fakes;

use Estratos\FinkokBundle\Csd\CsdEncoderInterface;

/**
 * Codificador de prueba que registra con qué argumentos se le llamó.
 *
 * Permite comprobar que Credentials le pasa la contraseña del panel, que es el
 * dato que Finkok necesita para descifrar la llave.
 */
final class RecordingCsdEncoder implements CsdEncoderInterface
{
    /** @var list<array{metodo: string, valores: list<string|null>}> */
    public array $calls = [];

    public function encodeCertificate(string $certificate): string
    {
        $this->calls[] = ['metodo' => 'certificado', 'valores' => [$certificate]];

        return 'certificado-codificado';
    }

    public function encodePrivateKey(string $privateKey, ?string $keyPassphrase, string $panelPassword): string
    {
        $this->calls[] = ['metodo' => 'llave', 'valores' => [$privateKey, $keyPassphrase, $panelPassword]];

        return 'llave-codificada';
    }
}