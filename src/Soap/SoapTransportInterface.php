<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Soap;

/**
 * Transporte SOAP desacoplado del cliente HTTP.
 *
 * El bundle incluye {@see HttpClientSoapTransport}, construido sobre Symfony
 * HttpClient, de modo que **no requiere `ext-soap`**. Implementar esta interfaz
 * permite sustituir el transporte en pruebas o inyectar un cliente con
 * interceptores propios.
 */
interface SoapTransportInterface
{
    /**
     * Envía la petición y devuelve la respuesta SOAP.
     *
     * @throws \Finkok\CfdiBundle\Exception\TransportException          fallo de red o HTTP
     * @throws \Finkok\CfdiBundle\Exception\SoapFaultException         SOAP Fault de protocolo
     * @throws \Finkok\CfdiBundle\Exception\UnexpectedResponseException respuesta no interpretable
     */
    public function send(SoapRequest $request): SoapResponse;
}
