<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Service;

use Finkok\CfdiBundle\Config\CredentialsInterface;
use Finkok\CfdiBundle\Config\CredentialsProviderInterface;
use Finkok\CfdiBundle\Config\EndpointResolver;
use Finkok\CfdiBundle\Config\Service;
use Finkok\CfdiBundle\Soap\SoapRequest;
use Finkok\CfdiBundle\Soap\SoapResponse;
use Finkok\CfdiBundle\Soap\SoapTransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Base común de los servicios de Finkok: resuelve el perfil de credenciales, el
 * endpoint del servicio y envía la petición por el transporte configurado.
 */
abstract class AbstractFinkokService
{
    protected readonly LoggerInterface $logger;

    public function __construct(
        protected readonly SoapTransportInterface $transport,
        protected readonly EndpointResolver $endpointResolver,
        protected readonly CredentialsProviderInterface $credentialsProvider,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Web Service al que pertenece la implementación.
     */
    abstract protected function service(): Service;

    /**
     * Resuelve el perfil a usar: el explícito o el configurado por defecto.
     */
    protected function resolveCredentials(?CredentialsInterface $credentials): CredentialsInterface
    {
        return $credentials ?? $this->credentialsProvider->get();
    }

    /**
     * Construye la petición sin enviarla.
     *
     * El orden de `$arguments` debe respetar la secuencia declarada en el WSDL,
     * porque los servicios document/literal de Finkok la validan.
     *
     * @param array<string, mixed> $arguments
     */
    protected function buildRequest(
        string $operation,
        array $arguments,
        CredentialsInterface $credentials,
    ): SoapRequest {
        return new SoapRequest(
            endpoint: $this->endpointResolver->resolve(
                $this->service(),
                $credentials->environment(),
                $credentials,
            ),
            operation: $operation,
            namespace: $this->service()->namespace(),
            arguments: $arguments,
        );
    }

    /**
     * Envía la petición y devuelve la respuesta SOAP.
     *
     * @param array<string, mixed> $arguments
     */
    protected function call(
        string $operation,
        array $arguments,
        CredentialsInterface $credentials,
    ): SoapResponse {
        $request = $this->buildRequest($operation, $arguments, $credentials);

        $this->logger->info('Finkok: invocando operación', [
            'servicio' => $this->service()->value,
            'operacion' => $operation,
            'ambiente' => $credentials->environment()->label(),
            'perfil' => $credentials->name(),
            'endpoint' => $request->endpoint,
        ]);

        return $this->transport->send($request);
    }

    /**
     * Argumentos de autenticación que comparten todas las operaciones.
     *
     * @return array<string, string>
     */
    protected function authenticationArguments(CredentialsInterface $credentials): array
    {
        return [
            'username' => $credentials->username(),
            'password' => $credentials->password(),
        ];
    }
}
