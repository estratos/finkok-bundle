<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Exception;

/**
 * Se solicitó un perfil de credenciales que no existe en la configuración.
 */
final class ProfileNotFoundException extends ConfigurationException
{
    /**
     * @param string[] $available
     */
    public static function forName(string $name, array $available): self
    {
        return new self(sprintf(
            'No existe el perfil de credenciales "%s". Perfiles configurados: %s. '
            .'Defínelo bajo la clave «finkok.profiles» o revisa el nombre solicitado.',
            $name,
            [] === $available ? '(ninguno)' : '"'.implode('", "', $available).'"',
        ));
    }
}
