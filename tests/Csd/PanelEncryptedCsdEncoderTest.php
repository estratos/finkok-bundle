<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Tests\Csd;

use Estratos\FinkokBundle\Csd\PanelEncryptedCsdEncoder;
use Estratos\FinkokBundle\Exception\ValidationException;
use Estratos\FinkokBundle\Tests\Concerns\ManagesTempFiles;
use PHPUnit\Framework\TestCase;

/**
 * El proceso que documenta Finkok: la llave se abre con su propia contraseña, se
 * vuelve a cifrar en DES3 con la contraseña del panel y se envía en base64.
 *
 * Las pruebas generan un par de llaves real para poder comprobar la ida y vuelta:
 * lo que el bundle produce debe poder abrirse con la contraseña del panel, que es
 * exactamente lo que hará Finkok.
 */
final class PanelEncryptedCsdEncoderTest extends TestCase
{
    use ManagesTempFiles;

    private const KEY_PASSPHRASE = 'clave-de-la-llave';
    private const PANEL_PASSWORD = 'contrasena-del-panel';

    protected function tearDown(): void
    {
        $this->cleanupTemporaryFiles();
    }

    /**
     * @return array{0: \OpenSSLAsymmetricKey, 1: string} la llave y su PEM cifrado
     */
    private function encryptedKeyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key, 'No fue posible generar la llave de prueba.');

        $pem = '';
        $exported = openssl_pkey_export($key, $pem, self::KEY_PASSPHRASE, [
            'encrypt_key' => true,
            'encrypt_key_cipher' => OPENSSL_CIPHER_3DES,
        ]);
        self::assertTrue($exported, 'No fue posible exportar la llave de prueba.');

        return [$key, $pem];
    }

    private function derFromPem(string $pem): string
    {
        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);
        self::assertIsString($der);

        return $der;
    }

    private function modulusOf(\OpenSSLAsymmetricKey $key): string
    {
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);

        return (string) ($details['rsa']['n'] ?? '');
    }

    public function testElCertificadoSeEnviaEnBase64ConSusEncabezados(): void
    {
        $pem = "-----BEGIN CERTIFICATE-----\n"
            .chunk_split(base64_encode('contenido-der-de-prueba'), 64, "\n")
            ."-----END CERTIFICATE-----\n";

        $encoded = (new PanelEncryptedCsdEncoder())->encodeCertificate($pem);
        $decoded = base64_decode($encoded, true);

        self::assertIsString($decoded);
        self::assertStringContainsString('-----BEGIN CERTIFICATE-----', $decoded);
        self::assertStringContainsString('-----END CERTIFICATE-----', $decoded);
        self::assertStringContainsString('contenido-der-de-prueba', base64_decode(
            (string) preg_replace('/-----[^-]+-----|\s+/', '', $decoded),
        ) ?: '');
    }

    public function testElCertificadoEnDerSeConvierteAPemAntesDeCodificar(): void
    {
        $der = random_bytes(96);

        $decoded = base64_decode((new PanelEncryptedCsdEncoder())->encodeCertificate($der), true);

        self::assertIsString($decoded);
        self::assertStringStartsWith('-----BEGIN CERTIFICATE-----', $decoded);
        self::assertSame($der, base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $decoded), true));
    }

    public function testLaLlaveSeCifraEnDes3ConLaContrasenaDelPanel(): void
    {
        [$key, $pem] = $this->encryptedKeyPair();

        $encoded = (new PanelEncryptedCsdEncoder())->encodePrivateKey($pem, self::KEY_PASSPHRASE, self::PANEL_PASSWORD);
        $encryptedPem = base64_decode($encoded, true);

        self::assertIsString($encryptedPem);
        self::assertStringContainsString('PRIVATE KEY-----', $encryptedPem, 'Debe seguir siendo un PEM.');

        // Esto es lo que hará Finkok: abrirla con la contraseña del panel.
        $recovered = openssl_pkey_get_private($encryptedPem, self::PANEL_PASSWORD);

        self::assertNotFalse($recovered, 'La llave debe poder abrirse con la contraseña del panel.');
        self::assertSame($this->modulusOf($key), $this->modulusOf($recovered), 'Debe ser la misma llave.');
    }

    public function testLaLlaveCifradaYaNoSeAbreConLaContrasenaDeLaLlave(): void
    {
        [, $pem] = $this->encryptedKeyPair();

        $encryptedPem = base64_decode(
            (new PanelEncryptedCsdEncoder())->encodePrivateKey($pem, self::KEY_PASSPHRASE, self::PANEL_PASSWORD),
            true,
        );

        self::assertIsString($encryptedPem);
        self::assertFalse(
            @openssl_pkey_get_private($encryptedPem, self::KEY_PASSPHRASE),
            'La contraseña del CSD no debe servir para descifrar el resultado.',
        );
    }

    public function testAceptaLaLlaveEnDerComoLaEntregaElSat(): void
    {
        [$key, $pem] = $this->encryptedKeyPair();

        $encoded = (new PanelEncryptedCsdEncoder())->encodePrivateKey(
            $this->derFromPem($pem),
            self::KEY_PASSPHRASE,
            self::PANEL_PASSWORD,
        );

        $recovered = openssl_pkey_get_private((string) base64_decode($encoded, true), self::PANEL_PASSWORD);

        self::assertNotFalse($recovered, 'El DER del SAT debe poder abrirse probando las cabeceras PEM.');
        self::assertSame($this->modulusOf($key), $this->modulusOf($recovered));
    }

    public function testAceptaRutasDeArchivo(): void
    {
        [, $pem] = $this->encryptedKeyPair();
        $path = $this->temporaryFile($this->derFromPem($pem), 'emisor.key');

        $encoded = (new PanelEncryptedCsdEncoder())->encodePrivateKey($path, self::KEY_PASSPHRASE, self::PANEL_PASSWORD);

        self::assertNotFalse(openssl_pkey_get_private((string) base64_decode($encoded, true), self::PANEL_PASSWORD));
    }

    public function testFallaSiLaContrasenaDeLaLlaveEsIncorrecta(): void
    {
        [, $pem] = $this->encryptedKeyPair();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/No fue posible abrir la llave privada/');

        (new PanelEncryptedCsdEncoder())->encodePrivateKey($pem, 'contrasena-equivocada', self::PANEL_PASSWORD);
    }

    public function testFallaSiNoHayContrasenaDelPanel(): void
    {
        [, $pem] = $this->encryptedKeyPair();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/contraseña del panel/');

        (new PanelEncryptedCsdEncoder())->encodePrivateKey($pem, self::KEY_PASSPHRASE, '   ');
    }

    public function testFallaConContenidoVacio(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/está vacío/');

        (new PanelEncryptedCsdEncoder())->encodeCertificate('  ');
    }
}