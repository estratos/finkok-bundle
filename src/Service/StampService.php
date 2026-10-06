<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Service;

use Estratos\FinkokBundle\Config\CredentialsInterface;
use Estratos\FinkokBundle\Config\CredentialsProviderInterface;
use Estratos\FinkokBundle\Config\EndpointResolver;
use Estratos\FinkokBundle\Config\Service;
use Estratos\FinkokBundle\Contract\StampServiceInterface;
use Estratos\FinkokBundle\Hydrator\QueryPendingResultHydrator;
use Estratos\FinkokBundle\Hydrator\StampReceiptHydrator;
use Estratos\FinkokBundle\Model\QueryPendingResult;
use Estratos\FinkokBundle\Model\StampReceipt;
use Estratos\FinkokBundle\Soap\SoapTransportInterface;
use Estratos\FinkokBundle\Soap\Value\Base64Value;
use Estratos\FinkokBundle\Xml\CfdiDocument;
use Estratos\FinkokBundle\Xml\CfdiPreflightValidator;
use Psr\Log\LoggerInterface;

/**
 * Implementación del Web Service de timbrado de Finkok sobre SOAP document/literal.
 */
final class StampService extends AbstractFinkokService implements StampServiceInterface
{
    public function __construct(
        SoapTransportInterface $transport,
        EndpointResolver $endpointResolver,
        CredentialsProviderInterface $credentialsProvider,
        private readonly CfdiPreflightValidator $validator,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($transport, $endpointResolver, $credentialsProvider, $logger);
    }

    public function stamp(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): StampReceipt
    {
        return $this->perform('stamp', $cfdi, $credentials);
    }

    public function quickStamp(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): StampReceipt
    {
        return $this->perform('quick_stamp', $cfdi, $credentials);
    }

    public function stamped(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): StampReceipt
    {
        return $this->perform('stamped', $cfdi, $credentials);
    }

    public function signStamp(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): StampReceipt
    {
        return $this->perform('sign_stamp', $cfdi, $credentials);
    }

    public function queryPending(string $uuid, ?CredentialsInterface $credentials = null): QueryPendingResult
    {
        $credentials ??= $this->resolveCredentials(null);

        // Orden según el WSDL: username, password, uuid.
        $response = $this->call('query_pending', array_merge(
            $this->authenticationArguments($credentials),
            ['uuid' => $uuid],
        ), $credentials);

        return QueryPendingResultHydrator::hydrate($response->result('query_pending'));
    }

    public function previewStampRequest(CfdiDocument|string $cfdi, ?CredentialsInterface $credentials = null): string
    {
        $credentials ??= $this->resolveCredentials(null);
        $document = CfdiDocument::coerce($cfdi);

        return $this->buildRequest('stamp', $this->stampArguments($document, $credentials), $credentials)->toXml();
    }

    /**
     * Ejecuta cualquiera de los cuatro métodos que comparten firma
     * (`xml`, `username`, `password`).
     */
    private function perform(
        string $operation,
        CfdiDocument|string $cfdi,
        ?CredentialsInterface $credentials,
    ): StampReceipt {
        $credentials ??= $this->resolveCredentials(null);
        $document = CfdiDocument::coerce($cfdi);

        foreach ($this->validator->validate($document) as $warning) {
            $this->logger->warning('Finkok: advertencia previa al timbrado', [
                'advertencia' => $warning,
                'cfdi' => $document->describe(),
            ]);
        }

        $response = $this->call(
            $operation,
            $this->stampArguments($document, $credentials),
            $credentials,
        );

        $receipt = StampReceiptHydrator::hydrate($response->result($operation), $response->raw());

        $this->logger->info('Finkok: resultado del timbrado', [
            'operacion' => $operation,
            'exito' => $receipt->isSuccess(),
            'uuid' => $receipt->uuid,
            'cod_estatus' => $receipt->status,
            'codigos_error' => $receipt->getErrorCodes(),
        ]);

        return $receipt;
    }

    /**
     * Orden según el WSDL de `stamp`: xml, username, password.
     *
     * @return array<string, mixed>
     */
    private function stampArguments(CfdiDocument $document, CredentialsInterface $credentials): array
    {
        return array_merge(
            ['xml' => Base64Value::fromBinary($document->content())],
            $this->authenticationArguments($credentials),
        );
    }

    protected function service(): Service
    {
        return Service::Stamp;
    }
}
