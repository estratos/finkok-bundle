<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Exception;

/**
 * Los datos de entrada no son utilizables: XML vacío, XML mal formado, UUID con
 * formato inválido, motivo de cancelación inexistente, archivo CSD ilegible, etc.
 *
 * Estas validaciones son locales (no llegan a Finkok) y existen para evitar
 * incidencias costosas como la 705 «XML estructura inválida» o la 301 «XML mal
 * formado».
 */
final class ValidationException extends \InvalidArgumentException implements FinkokExceptionInterface
{
    public static function forEmptyXml(): self
    {
        return new self('El XML a enviar está vacío.');
    }

    public static function forMalformedXml(string $reason): self
    {
        return new self(sprintf(
            'El XML está mal formado y Finkok lo rechazaría con la incidencia 301: %s',
            $reason,
        ));
    }

    public static function forOversizedXml(int $bytes, int $limit): self
    {
        return new self(sprintf(
            'El XML pesa %d bytes y supera el límite de %d bytes (1 MB) que impone Finkok. '
            .'Superar ese tamaño puede afectar el timbrado de comprobantes posteriores.',
            $bytes,
            $limit,
        ));
    }

    public static function forUnexpectedRootElement(?string $actual, string $expected): self
    {
        return new self(sprintf(
            'El elemento raíz del XML es "%s" y se esperaba "%s" (cfdi:Comprobante). '
            .'Verifica que estés enviando un CFDI y no, por ejemplo, una retención o una respuesta del SAT.',
            $actual ?? '(desconocido)',
            $expected,
        ));
    }

    public static function forInvalidUuid(string $uuid): self
    {
        return new self(sprintf(
            'El UUID "%s" no tiene el formato de un Folio Fiscal (8-4-4-4-12 caracteres hexadecimales en mayúsculas).',
            $uuid,
        ));
    }

    public static function forEmptyArgument(string $argument): self
    {
        return new self(sprintf('El argumento "%s" es obligatorio y no puede estar vacío.', $argument));
    }

    public static function forUnreadableFile(string $path, string $what): self
    {
        return new self(sprintf('No se puede leer el archivo %s "%s".', $what, $path));
    }
}
