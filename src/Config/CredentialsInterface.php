<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Config;

/**
 * Credenciales y datos fiscales con los que se consume un Web Service de Finkok.
 *
 * En Finkok cada RFC emisor se registra por separado en el panel (sección
 * «Clientes») con sus propios timbres, su propio estatus y, si aplica, sus
 * propios CSD. Por eso un perfil agrupa: usuario, contraseña, ambiente, RFC
 * emisor y —opcionalmente— el certificado y la llave para cancelar.
 */
interface CredentialsInterface
{
    /**
     * Nombre del perfil en la configuración (por ejemplo `matriz` o `sucursal_norte`).
     */
    public function name(): string;

    /**
     * Usuario del panel de Finkok. Es el mismo para todos los RFC de la cuenta.
     */
    public function username(): string;

    /**
     * Contraseña del panel de Finkok.
     */
    public function password(): string;

    /**
     * RFC del emisor. Es obligatorio para cancelar (`taxpayer_id`) y opcional
     * para timbrar, ya que el timbrado toma el RFC del XML.
     */
    public function taxpayerId(): ?string;

    /**
     * RFC del emisor, obligatorio.
     *
     * @throws \Finkok\CfdiBundle\Exception\ConfigurationException si no está configurado
     */
    public function requireTaxpayerId(): string;

    /**
     * Ambiente de Finkok al que apunta el perfil.
     */
    public function environment(): Environment;

    /**
     * Valor listo para el parámetro `cer` del método `cancel`, o `null` si el
     * perfil no tiene CSD configurado (Finkok usará el certificado cargado en el
     * panel para ese RFC).
     */
    public function certificateBase64(): ?string;

    /**
     * Valor listo para el parámetro `key` del método `cancel`, o `null`.
     */
    public function privateKeyBase64(): ?string;

    public function hasCsd(): bool;

    /**
     * URL de endpoint sobrescrita por el perfil, o `null` para usar la global.
     */
    public function endpoint(Service $service, Environment $environment): ?string;

    /**
     * Devuelve una copia del perfil apuntando a otro ambiente (útil para alternar
     * DEMO y PRODUCCIÓN sin duplicar la configuración).
     */
    public function withEnvironment(Environment $environment): static;
}
