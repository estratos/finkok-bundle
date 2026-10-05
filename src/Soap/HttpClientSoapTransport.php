<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Soap;

use Finkok\CfdiBundle\Exception\TransportException as FinkokTransportException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportExceptionInterface as HttpClientTransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Transporte SOAP sobre Symfony HttpClient.
 *
 * No depende de `ext-soap`: construye el envelope XML, lo envía por POST con las
 * cabeceras SOAP 1.1 y parsea la respuesta. Esto permite probar el bundle con
 * `MockHttpClient`, inspeccionar el XML exacto que se envía a Finkok y funcionar
 * en instalaciones de PHP donde la extensión SOAP no está disponible.
 */
final class HttpClientSoapTransport implements SoapTransportInterface
{
    public const DEFAULT_TIMEOUT = 30.0;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
        ?LoggerInterface $logger = null,
        /**
         * Cuando es `true`, el envelope completo se escribe en el log. Debe
         * permanecer desactivado en producción porque el XML contiene datos
         * fiscales y, en el caso de la cancelación, los CSD en base64.
         */
        private readonly bool $logPayloads = false,
        private readonly string $userAgent = 'finkok-cfdi-bundle/1.0',
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function send(SoapRequest $request): SoapResponse
    {
        $payload = $request->toXml();

        $this->logger->debug('Finkok: enviando petición SOAP', [
            'operacion' => $request->operation,
            'endpoint' => $request->endpoint,
            'argumentos' => $request->debugArguments(),
            'payload' => $this->logPayloads ? $payload : sprintf('<%d bytes omitidos>', \strlen($payload)),
        ]);

        $startedAt = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $request->endpoint, [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '"'.$request->soapAction().'"',
                    'Accept' => 'text/xml, application/soap+xml, */*',
                    'User-Agent' => $this->userAgent,
                ],
                'body' => $payload,
                'timeout' => $request->timeout ?? $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $headers = $response->getHeaders(false);
        } catch (HttpClientTransportExceptionInterface $exception) {
            $this->logger->error('Finkok: fallo de conexión', [
                'operacion' => $request->operation,
                'endpoint' => $request->endpoint,
                'excepcion' => $exception->getMessage(),
            ]);

            throw FinkokTransportException::forConnectionFailure(
                $request->endpoint,
                $request->operation,
                $exception,
            );
        }

        $elapsed = round((microtime(true) - $startedAt) * 1000, 2);
        $soapResponse = new SoapResponse($content, $statusCode, $headers);

        $this->logger->debug('Finkok: respuesta recibida', [
            'operacion' => $request->operation,
            'http' => $statusCode,
            'ms' => $elapsed,
            'fault' => $soapResponse->fault()?->describe(),
        ]);

        // Un SOAP Fault trae más información que el código HTTP, así que se
        // evalúa primero incluso cuando el HTTP es 500.
        $soapResponse->assertNoFault($request->endpoint, $request->operation);

        if ($statusCode >= 400) {
            throw FinkokTransportException::forHttpError(
                $request->endpoint,
                $request->operation,
                $statusCode,
                $content,
            );
        }

        if (!$soapResponse->isSoapEnvelope()) {
            throw new \Finkok\CfdiBundle\Exception\UnexpectedResponseException(
                sprintf(
                    'Finkok respondió HTTP %d con un cuerpo que no es un envelope SOAP%s al invocar %s en %s. '
                    .'Verifica que la URL corresponda al Web Service esperado '
                    .'(por ejemplo, que no sea la de retenciones) y que no haya un proxy intermediando.',
                    $statusCode,
                    $soapResponse->isXml() ? ' válido' : ' ni XML válido',
                    $request->operation,
                    $request->endpoint,
                ),
                $request->endpoint,
                $request->operation,
                $content,
            );
        }

        return $soapResponse;
    }
}
