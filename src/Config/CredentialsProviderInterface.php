<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Config;

/**
 * Resuelve los perfiles de credenciales configurados.
 *
 * El bundle expone el proveedor como servicio (`finkok.credentials_provider`)
 * para que la aplicación pueda obtener el perfil del emisor que necesita y
 * pasarlo explícitamente a cada llamada:
 *
 * ```php
 * $emisor = $provider->get('sucursal_norte');
 * $stamp->stamp($cfdi, $emisor);
 * ```
 *
 * El paso explícito —en lugar de un «perfil activo» global— mantiene el bundle
 * seguro en workers de larga vida (Messenger, RoadRunner, Swoole), donde el
 * estado global provoca que un comprobante se timbre con el RFC equivocado.
 */
interface CredentialsProviderInterface
{
    /**
     * Devuelve el perfil indicado, o el perfil por defecto si `$name` es `null`.
     *
     * @throws \Finkok\CfdiBundle\Exception\ProfileNotFoundException
     */
    public function get(?string $name = null): CredentialsInterface;

    /**
     * `true` si existe un perfil con ese nombre.
     */
    public function has(string $name): bool;

    /**
     * Perfil por defecto.
     *
     * @throws \Finkok\CfdiBundle\Exception\ProfileNotFoundException
     */
    public function default(): CredentialsInterface;

    /**
     * Nombres de todos los perfiles configurados.
     *
     * @return list<string>
     */
    public function names(): array;
}
