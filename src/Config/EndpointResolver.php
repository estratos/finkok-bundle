<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Config;

/**
 * Resuelve la URL del endpoint SOAP aplicando la precedencia:
 *
 * 1. sobrescritura del perfil de credenciales (por si un emisor usa otro host);
 * 2. sobrescritura global de `finkok.endpoints`;
 * 3. valor por defecto del bundle, derivado del ambiente.
 */
final class EndpointResolver
{
    /**
     * @param array<string, array<string, string>> $endpoints servicio => ambiente => URL
     */
    public function __construct(private readonly array $endpoints = [])
    {
    }

    public function resolve(
        Service $service,
        Environment $environment,
        ?CredentialsInterface $credentials = null,
    ): string {
        $fromProfile = $credentials?->endpoint($service, $environment);

        if (null !== $fromProfile) {
            return $fromProfile;
        }

        $fromConfig = $this->endpoints[$service->value][$environment->value] ?? null;

        if (null !== $fromConfig && '' !== trim($fromConfig)) {
            return $fromConfig;
        }

        return $service->defaultEndpoint($environment);
    }
}
