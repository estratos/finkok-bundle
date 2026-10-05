<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Tests\Concerns;

use Finkok\CfdiBundle\Config\Credentials;
use Finkok\CfdiBundle\Config\CredentialsProvider;
use Finkok\CfdiBundle\Config\EndpointResolver;
use Finkok\CfdiBundle\Config\Environment;
use Finkok\CfdiBundle\Service\CancelService;
use Finkok\CfdiBundle\Service\StampService;
use Finkok\CfdiBundle\Soap\HttpClientSoapTransport;
use Finkok\CfdiBundle\Soap\SoapTransportInterface;
use Finkok\CfdiBundle\Xml\CfdiPreflightValidator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Utilidades compartidas por las pruebas de servicios.
 */
trait InteractsWithFinkok
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $recordedRequests = [];

    /**
     * Cliente HTTP simulado que registra cada petición y responde en orden.
     *
     * @param list<MockResponse> $responses
     */
    private function mockHttpClient(array $responses): MockHttpClient
    {
        $this->recordedRequests = [];
        $queue = $responses;

        return new MockHttpClient(function (string $method, string $url, array $options) use (&$queue): MockResponse {
            $this->recordedRequests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            if ([] === $queue) {
                throw new \RuntimeException('La prueba agotó las respuestas simuladas.');
            }

            return array_shift($queue);
        });
    }

    private function transport(MockHttpClient $client, float $timeout = 30.0): SoapTransportInterface
    {
        return new HttpClientSoapTransport($client, $timeout);
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function stampService(array $responses, bool $preflight = true): StampService
    {
        return new StampService(
            $this->transport($this->mockHttpClient($responses)),
            new EndpointResolver(),
            $this->credentialsProvider(),
            new CfdiPreflightValidator($preflight),
        );
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function cancelService(array $responses): CancelService
    {
        return new CancelService(
            $this->transport($this->mockHttpClient($responses)),
            new EndpointResolver(),
            $this->credentialsProvider(),
        );
    }

    private function credentialsProvider(): CredentialsProvider
    {
        return new CredentialsProvider(
            [
                'default' => $this->credentials(),
                'sucursal' => $this->credentials(
                    username: 'sucursal@demo.com',
                    password: 'otra-clave',
                    taxpayerId: 'MISC491214B86',
                    environment: Environment::Production,
                    name: 'sucursal',
                ),
            ],
            'default',
        );
    }

    private function credentials(
        string $username = 'usuario@demo.com',
        string $password = 'clave-secreta',
        string $taxpayerId = 'EKU9003173C9',
        Environment $environment = Environment::Demo,
        string $name = 'default',
    ): Credentials {
        return new Credentials($name, $username, $password, $taxpayerId, $environment);
    }

    /**
     * Cuerpo del último envío (el envelope SOAP serializado).
     */
    private function lastRequestBody(): string
    {
        $request = $this->recordedRequests[array_key_last($this->recordedRequests)] ?? null;

        if (null === $request) {
            throw new \RuntimeException('No se registró ninguna petición HTTP.');
        }

        return (string) ($request['options']['body'] ?? '');
    }

    private function lastRequestUrl(): string
    {
        return $this->recordedRequests[array_key_last($this->recordedRequests)]['url'] ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function firstRequestOptions(): array
    {
        return $this->recordedRequests[0]['options'] ?? [];
    }
}
