<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Model;

/**
 * Contrato común de los resultados devueltos por los Web Services de Finkok.
 *
 * Finkok comunica los errores de negocio dentro de una respuesta SOAP válida
 * (en `CodEstatus` y/o en `<Incidencias>`), no mediante SOAP Fault. Por eso los
 * métodos del bundle devuelven DTOs que implementan esta interfaz y el error de
 * negocio se inspecciona —o se propaga con `assertSuccess()`— de forma explícita.
 */
interface FinkokResultInterface
{
    /**
     * `true` cuando la operación produjo el efecto esperado (por ejemplo, el CFDI
     * quedó timbrado o la cancelación fue aceptada).
     */
    public function isSuccess(): bool;

    /**
     * Texto de `CodEstatus` tal como lo devolvió Finkok, sin transformar.
     */
    public function getStatusCode(): ?string;

    /**
     * Incidencias reportadas por Finkok (puede haberlas incluso en una operación
     * exitosa).
     */
    public function getIncidences(): IncidenceCollection;

    /**
     * Códigos de error crudos devueltos por Finkok.
     *
     * @return list<string>
     */
    public function getErrorCodes(): array;

    /**
     * Mensaje de error legible, o `null` si la operación fue exitosa.
     */
    public function getErrorMessage(): ?string;

    public function hasErrorCode(ErrorCode $code): bool;

    /**
     * Lanza {@see \Finkok\CfdiBundle\Exception\ApiException} si la operación no
     * fue exitosa.
     *
     * @throws \Finkok\CfdiBundle\Exception\ApiException
     */
    public function assertSuccess(): static;
}
