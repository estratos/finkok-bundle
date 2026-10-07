<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Config;

use Estratos\FinkokBundle\Csd\CsdEncoderInterface;
use Estratos\FinkokBundle\Csd\PanelEncryptedCsdEncoder;
use Estratos\FinkokBundle\Exception\ConfigurationException;

/**
 * Implementación inmutable de un perfil de credenciales.
 *
 * Los valores del CSD se codifican de forma perezosa y memorizada: si el perfil
 * no se usa para cancelar, nunca se lee el archivo del disco.
 */
final class Credentials implements CredentialsInterface
{
    private readonly CsdEncoderInterface $csdEncoder;

    private ?string $certificatePayload = null;

    private ?string $privateKeyPayload = null;

    private bool $csdResolved = false;

    /**
     * @param array<string, array<string, string>> $endpoints  sobrescritura de
     *                                                         endpoints: servicio => ambiente => URL
     */
    public function __construct(
        private readonly string $name,
        private readonly string $username,
        private readonly string $password,
        private readonly ?string $taxpayerId = null,
        private readonly Environment $environment = Environment::Demo,
        /** Ruta al archivo `.cer` o su contenido en PEM. */
        private readonly ?string $certificate = null,
        /** Ruta al archivo `.key` o su contenido en PEM. */
        private readonly ?string $privateKey = null,
        /** Contraseña propia de la llave privada. */
        private readonly ?string $privateKeyPassphrase = null,
        private readonly array $endpoints = [],
        ?CsdEncoderInterface $csdEncoder = null,
    ) {
        $this->csdEncoder = $csdEncoder ?? new PanelEncryptedCsdEncoder();

        if ('' === trim($username)) {
            throw new ConfigurationException(sprintf('El perfil "%s" no define el usuario de Finkok.', $name));
        }

        if ('' === trim($password)) {
            throw new ConfigurationException(sprintf('El perfil "%s" no define la contraseña de Finkok.', $name));
        }

        if ((null === $certificate) xor (null === $privateKey)) {
            throw new ConfigurationException(sprintf(
                'El perfil "%s" debe definir el certificado y la llave privada juntos, o ninguno de los dos.',
                $name,
            ));
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function taxpayerId(): ?string
    {
        if (null === $this->taxpayerId || '' === trim($this->taxpayerId)) {
            return null;
        }

        return strtoupper(trim($this->taxpayerId));
    }

    public function requireTaxpayerId(): string
    {
        $taxpayerId = $this->taxpayerId();

        if (null === $taxpayerId) {
            throw new ConfigurationException(sprintf(
                'El perfil "%s" no define "taxpayer_id" (RFC del emisor), requerido para cancelar. '
                .'Agrégalo en la configuración del perfil o pásalo explícitamente en la llamada.',
                $this->name,
            ));
        }

        return $taxpayerId;
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    public function certificateBase64(): ?string
    {
        $this->resolveCsd();

        return $this->certificatePayload;
    }

    public function privateKeyBase64(): ?string
    {
        $this->resolveCsd();

        return $this->privateKeyPayload;
    }

    public function hasCsd(): bool
    {
        return null !== $this->certificate && null !== $this->privateKey;
    }

    public function endpoint(Service $service, Environment $environment): ?string
    {
        $url = $this->endpoints[$service->value][$environment->value] ?? null;

        return null !== $url && '' !== trim($url) ? $url : null;
    }

    public function withEnvironment(Environment $environment): static
    {
        if ($environment === $this->environment) {
            return $this;
        }

        return new self(
            $this->name,
            $this->username,
            $this->password,
            $this->taxpayerId,
            $environment,
            $this->certificate,
            $this->privateKey,
            $this->privateKeyPassphrase,
            $this->endpoints,
            $this->csdEncoder,
        );
    }

    /**
     * Nunca expone la contraseña al volcar el objeto (por ejemplo en un `dump()`
     * o en el profiler de Symfony).
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'password' => '***',
            'taxpayer_id' => $this->taxpayerId(),
            'environment' => $this->environment->value,
            'has_csd' => $this->hasCsd(),
            'endpoints' => array_keys($this->endpoints),
        ];
    }

    private function resolveCsd(): void
    {
        if ($this->csdResolved) {
            return;
        }

        $this->csdResolved = true;

        if (!$this->hasCsd()) {
            return;
        }

        $this->certificatePayload = $this->csdEncoder->encodeCertificate((string) $this->certificate);
        $this->privateKeyPayload = $this->csdEncoder->encodePrivateKey(
            (string) $this->privateKey,
            $this->privateKeyPassphrase,
            // Finkok descifra la llave con la contraseña del panel.
            $this->password,
        );
    }
}
