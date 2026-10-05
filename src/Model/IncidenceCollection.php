<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Model;

/**
 * Colección inmutable de {@see Incidence} con ayudas de consulta.
 *
 * @implements \IteratorAggregate<int, Incidence>
 */
final class IncidenceCollection implements \Countable, \IteratorAggregate
{
    /** @var list<Incidence> */
    private readonly array $incidences;

    /**
     * @param iterable<Incidence> $incidences
     */
    public function __construct(iterable $incidences = [])
    {
        $list = [];
        foreach ($incidences as $incidence) {
            $list[] = $incidence;
        }

        $this->incidences = $list;
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @return list<Incidence>
     */
    public function toArray(): array
    {
        return $this->incidences;
    }

    public function count(): int
    {
        return \count($this->incidences);
    }

    public function isEmpty(): bool
    {
        return [] === $this->incidences;
    }

    public function isNotEmpty(): bool
    {
        return [] !== $this->incidences;
    }

    /**
     * @return \Traversable<int, Incidence>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->incidences);
    }

    public function first(): ?Incidence
    {
        return $this->incidences[0] ?? null;
    }

    public function last(): ?Incidence
    {
        return [] === $this->incidences ? null : $this->incidences[\count($this->incidences) - 1];
    }

    /**
     * Códigos de error crudos tal como los devolvió Finkok (por ejemplo "705").
     *
     * @return list<string>
     */
    public function codes(): array
    {
        $codes = [];
        foreach ($this->incidences as $incidence) {
            if (null !== $incidence->code && '' !== trim($incidence->code)) {
                $codes[] = trim($incidence->code);
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Códigos reconocidos y tipificados por el bundle.
     *
     * @return list<ErrorCode>
     */
    public function errorCodes(): array
    {
        $codes = [];
        foreach ($this->incidences as $incidence) {
            $code = $incidence->errorCode();
            if (null !== $code && !\in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    public function hasCode(string $code): bool
    {
        return \in_array(strtoupper(trim($code)), array_map('strtoupper', $this->codes()), true);
    }

    public function hasErrorCode(ErrorCode $code): bool
    {
        return \in_array($code, $this->errorCodes(), true);
    }

    public function findByCode(string $code): ?Incidence
    {
        foreach ($this->incidences as $incidence) {
            if (null !== $incidence->code && 0 === strcasecmp(trim($incidence->code), trim($code))) {
                return $incidence;
            }
        }

        return null;
    }

    /**
     * Incidencias cuyo código es transitorio (vale la pena reintentar).
     */
    public function transient(): self
    {
        return $this->filter(static fn (Incidence $i): bool => true === $i->errorCode()?->isTransient());
    }

    /**
     * Incidencias que requieren intervención manual (panel de Finkok o SAT).
     */
    public function requiringManualAction(): self
    {
        return $this->filter(static fn (Incidence $i): bool => true === $i->errorCode()?->requiresManualAction());
    }

    /**
     * @param callable(Incidence): bool $predicate
     */
    public function filter(callable $predicate): self
    {
        return new self(array_values(array_filter($this->incidences, $predicate)));
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        $messages = [];
        foreach ($this->incidences as $incidence) {
            if (null !== $incidence->message && '' !== trim($incidence->message)) {
                $messages[] = trim($incidence->message);
            }
        }

        return $messages;
    }

    /**
     * Todas las incidencias en un solo texto, útil para excepciones y logs.
     */
    public function describe(string $separator = '; '): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        return implode($separator, array_map(
            static fn (Incidence $incidence): string => $incidence->describe(),
            $this->incidences,
        ));
    }

    public function __toString(): string
    {
        return $this->describe();
    }
}
