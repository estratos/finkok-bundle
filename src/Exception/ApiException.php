<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Exception;

use Estratos\FinkokBundle\Model\ErrorCode;
use Estratos\FinkokBundle\Model\FinkokResultInterface;
use Estratos\FinkokBundle\Model\IncidenceCollection;

/**
 * Finkok procesó la petición y respondió con una incidencia de negocio, es decir,
 * la operación no produjo el efecto esperado (timbrado o cancelación).
 *
 * Se lanza únicamente cuando el consumidor invoca `assertSuccess()` sobre un DTO
 * de resultado, o cuando el bundle necesita abortar por una precondición de
 * negocio. La excepción conserva las incidencias originales para que puedan
 * registrarse o mostrarse al usuario final.
 */
final class ApiException extends FinkokException
{
    private readonly IncidenceCollection $incidences;

    public function __construct(
        string $message,
        private readonly ?string $statusCode = null,
        ?IncidenceCollection $incidences = null,
        private readonly ?string $operation = null,
        private readonly ?FinkokResultInterface $result = null,
        ?\Throwable $previous = null,
    ) {
        $this->incidences = $incidences ?? IncidenceCollection::empty();

        parent::__construct($message, 0, $previous);
    }

    /**
     * Construye la excepción a partir de un resultado de Finkok no exitoso.
     */
    public static function fromResult(FinkokResultInterface $result, ?string $operation = null): self
    {
        $incidences = $result->getIncidences();

        $detail = $incidences->isNotEmpty()
            ? $incidences->describe()
            : (string) ($result->getStatusCode() ?? 'sin detalle');

        $statusCode = $result->getStatusCode();

        return new self(
            sprintf(
                'Finkok no completó la operación%s. CodEstatus: %s. Incidencias: %s',
                null !== $operation ? ' '.$operation : '',
                null !== $statusCode && '' !== trim($statusCode) ? trim($statusCode) : '(vacío)',
                $detail,
            ),
            $statusCode,
            $incidences,
            $operation,
            $result,
        );
    }

    /**
     * Texto de `CodEstatus` devuelto por Finkok.
     */
    public function getStatusCode(): ?string
    {
        return $this->statusCode;
    }

    public function getIncidences(): IncidenceCollection
    {
        return $this->incidences;
    }

    /**
     * @return list<string>
     */
    public function getErrorCodes(): array
    {
        return $this->incidences->codes();
    }

    /**
     * @return list<ErrorCode>
     */
    public function getTypedErrorCodes(): array
    {
        return $this->incidences->errorCodes();
    }

    public function hasErrorCode(ErrorCode $code): bool
    {
        return $this->incidences->hasErrorCode($code);
    }

    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Resultado original completo, por si la aplicación necesita la respuesta
     * íntegra de Finkok (por ejemplo el XML recuperado en un 307).
     */
    public function getResult(): ?FinkokResultInterface
    {
        return $this->result;
    }

    /**
     * La incidencia se resuelve reintentando la misma petición.
     */
    public function isTransient(): bool
    {
        return $this->incidences->transient()->isNotEmpty();
    }

    /**
     * La incidencia requiere una acción en el panel de Finkok o ante el SAT.
     */
    public function requiresManualAction(): bool
    {
        return $this->incidences->requiringManualAction()->isNotEmpty();
    }
}
