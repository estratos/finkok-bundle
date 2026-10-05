<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Config;

/**
 * Web Services de Finkok soportados por este bundle.
 *
 * Cada caso representa un WSDL independiente con su propio targetNamespace y su
 * propia URL por ambiente.
 */
enum Service: string
{
    /** Timbrado de CFDI (stamp.wsdl). */
    case Stamp = 'stamp';

    /** Cancelación de CFDI (cancel.wsdl). */
    case Cancel = 'cancel';

    /**
     * targetNamespace SOAP del servicio, requerido para construir el envelope
     * (los WSDL de Finkok son document/literal con elementFormDefault="qualified").
     */
    public function namespace(): string
    {
        return match ($this) {
            self::Stamp => 'http://facturacion.finkok.com/stamp',
            self::Cancel => 'http://facturacion.finkok.com/cancel',
        };
    }

    /**
     * Ruta del WSDL dentro del host de Finkok (sin la URL base).
     */
    public function wsdlPath(): string
    {
        return match ($this) {
            self::Stamp => '/servicios/soap/stamp.wsdl',
            self::Cancel => '/servicios/soap/cancel.wsdl',
        };
    }

    /**
     * URL del endpoint SOAP (sin extensión) para el ambiente indicado.
     */
    public function defaultEndpoint(Environment $environment): string
    {
        $host = $environment->isProduction()
            ? 'https://facturacion.finkok.com'
            : 'https://demo-facturacion.finkok.com';

        return $host.'/servicios/soap/'.$this->value;
    }

    /**
     * URL pública del WSDL, útil para depuración o generación de clientes.
     */
    public function wsdlUrl(Environment $environment): string
    {
        $host = $environment->isProduction()
            ? 'https://facturacion.finkok.com'
            : 'https://demo-facturacion.finkok.com';

        return $host.$this->wsdlPath();
    }
}
