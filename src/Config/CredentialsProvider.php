<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Config;

use Estratos\FinkokBundle\Exception\ConfigurationException;
use Estratos\FinkokBundle\Exception\ProfileNotFoundException;

/**
 * Proveedor de credenciales basado en la configuración del bundle.
 */
final class CredentialsProvider implements CredentialsProviderInterface
{
    /**
     * @param array<string, CredentialsInterface> $profiles perfiles indexados por nombre
     * @param string|null                         $default  nombre del perfil por defecto
     */
    public function __construct(
        private readonly array $profiles,
        private readonly ?string $default = null,
    ) {
        if ([] === $profiles) {
            throw new ConfigurationException(
                'No hay perfiles de Finkok configurados. Define al menos uno bajo la clave «finkok.profiles».',
            );
        }
    }

    public function get(?string $name = null): CredentialsInterface
    {
        if (null === $name || '' === trim($name)) {
            return $this->default();
        }

        if (!isset($this->profiles[$name])) {
            throw ProfileNotFoundException::forName($name, $this->names());
        }

        return $this->profiles[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->profiles[$name]);
    }

    public function default(): CredentialsInterface
    {
        if (null !== $this->default) {
            if (!isset($this->profiles[$this->default])) {
                throw ProfileNotFoundException::forName($this->default, $this->names());
            }

            return $this->profiles[$this->default];
        }

        // Sin perfil explícito, un único perfil configurado es el default natural.
        if (1 === \count($this->profiles)) {
            return $this->profiles[array_key_first($this->profiles)];
        }

        throw new ConfigurationException(sprintf(
            'Hay %d perfiles de Finkok configurados y no se definió «finkok.default_profile». '
            .'Indica el perfil por defecto o pásalo explícitamente en cada llamada. Perfiles: "%s".',
            \count($this->profiles),
            implode('", "', $this->names()),
        ));
    }

    public function names(): array
    {
        return array_map('strval', array_keys($this->profiles));
    }
}
