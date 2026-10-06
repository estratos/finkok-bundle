<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Config;

use Estratos\FinkokBundle\Exception\ConfigurationException;

/**
 * Proveedor de último recurso para cuando la aplicación no ha registrado el suyo.
 *
 * El bundle no lee credenciales de su propia configuración: los perfiles los crea
 * la aplicación que consume los servicios, porque pueden venir de variables de
 * entorno, de la base de datos o del inquilino activo.
 *
 * Este proveedor permite que el contenedor compile siempre —de modo que instalar
 * el bundle no rompa aplicaciones que todavía no lo configuran— y falla con un
 * mensaje accionable en cuanto alguien intenta usar un servicio de Finkok sin
 * haber registrado credenciales.
 *
 * @internal
 */
final class UnconfiguredCredentialsProvider implements CredentialsProviderInterface
{
    private const MESSAGE = 'La aplicación no ha registrado un proveedor de credenciales de Finkok.'
        .' El bundle no lee credenciales de su propia configuración: los perfiles los crea la'
        .' aplicación. Registra un servicio que implemente Estratos\FinkokBundle\Config\CredentialsProviderInterface,'
        .' por ejemplo con la clase Estratos\FinkokBundle\Config\CredentialsProvider del propio bundle.'
        .' Consulta la sección «Configuración» del README.';

    public function get(?string $name = null): CredentialsInterface
    {
        throw new ConfigurationException(self::MESSAGE);
    }

    public function has(string $name): bool
    {
        return false;
    }

    public function default(): CredentialsInterface
    {
        throw new ConfigurationException(self::MESSAGE);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return [];
    }
}
