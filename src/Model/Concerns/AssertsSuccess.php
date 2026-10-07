<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Model\Concerns;

use Estratos\FinkokBundle\Exception\ApiException;
use Estratos\FinkokBundle\Model\ErrorCode;
use Estratos\FinkokBundle\Model\IncidenceCollection;
/**
 * Implementación por defecto de las operaciones derivadas de
 * {@see \Estratos\FinkokBundle\Model\FinkokResultInterface}.
 *
 * La clase que use este trait debe implementar `isSuccess()`, `getStatusCode()`
 * y `getIncidences()`.
 */
trait AssertsSuccess
{
    abstract public function isSuccess(): bool;

    abstract public function getStatusCode(): ?string;

    abstract public function getIncidences(): IncidenceCollection;

    /**
     * @return list<string>
     */
    public function getErrorCodes(): array
    {
        return $this->getIncidences()->codes();
    }

    public function getErrorMessage(): ?string
    {
        if ($this->isSuccess()) {
            return null;
        }

        $messages = $this->getIncidences()->messages();
        if ([] !== $messages) {
            return implode('; ', $messages);
        }

        $status = $this->getStatusCode();

        return null !== $status && '' !== trim($status)
            ? trim($status)
            : 'Finkok no devolvió detalle del error.';
    }

    public function hasErrorCode(ErrorCode $code): bool
    {
        return $this->getIncidences()->hasErrorCode($code);
    }

    /**
     * `true` cuando Finkok rechazó la petición por credenciales inválidas.
     *
     * Los dos Web Services lo reportan de forma distinta: el de timbrado usa la
     * incidencia 300 («El usuario o contraseña son inválidos»); el de cancelación
     * responde el código 300 (`CodEstatus`) o el texto «Invalid Username or
     * Password» en `error`. Este método unifica los tres casos; las
     * implementaciones reconocen sus propios códigos con
     * {@see self::isCredentialStatusCode()}.
     *
     * Causas habituales: usuario o contraseña incorrectos, o usar la URL de un
     * ambiente con las credenciales del otro.
     */
    public function isCredentialError(): bool
    {
        if ($this->isCredentialStatusCode()
            || $this->getIncidences()->hasErrorCode(ErrorCode::InvalidCredentials)) {
            return true;
        }

        $haystack = strtolower(implode(' ', array_filter([
            (string) $this->getStatusCode(),
            ...$this->getIncidences()->messages(),
        ])));

        if ('' === trim($haystack)) {
            return false;
        }

        return str_contains($haystack, 'invalid')
            && (str_contains($haystack, 'password')
                || str_contains($haystack, 'username')
                || str_contains($haystack, 'contraseña'));
    }

    /**
     * Punto de extensión para que un resultado reconozca sus propios códigos de
     * credenciales. El Web Service de cancelación responde 300 («Usuario no
     * válido») en `CodEstatus`, un valor que el texto por sí solo no delata.
     */
    protected function isCredentialStatusCode(): bool
    {
        return false;
    }

    /**
     * @throws ApiException
     */
    public function assertSuccess(): static
    {
        if (!$this->isSuccess()) {
            throw ApiException::fromResult($this);
        }

        return $this;
    }

    /**
     * `true` si la operación debe reintentarse tal cual.
     */
    public function isTransient(): bool
    {
        return $this->getIncidences()->transient()->isNotEmpty();
    }

    /**
     * `true` si el error exige intervención en el panel de Finkok o ante el SAT.
     */
    public function requiresManualAction(): bool
    {
        return $this->getIncidences()->requiringManualAction()->isNotEmpty();
    }

    /**
     * Resumen para logs: estado, códigos e incidencias.
     */
    public function describe(): string
    {
        $parts = [sprintf('%s: %s', static::class, $this->isSuccess() ? 'OK' : 'ERROR')];

        $status = $this->getStatusCode();
        if (null !== $status && '' !== trim($status)) {
            $parts[] = 'CodEstatus='.trim($status);
        }

        $codes = $this->getErrorCodes();
        if ([] !== $codes) {
            $parts[] = 'Codigos='.implode(',', $codes);
        }

        $incidences = $this->getIncidences()->describe();
        if ('' !== $incidences) {
            $parts[] = $incidences;
        }

        return implode(' | ', $parts);
    }

    public function __toString(): string
    {
        return $this->describe();
    }
}
