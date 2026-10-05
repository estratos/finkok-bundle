<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Exception;

/**
 * Marcador común de todas las excepciones lanzadas por el bundle.
 *
 * Permite capturar cualquier error del bundle con un único `catch`:
 *
 * ```php
 * try {
 *     $receipt = $stamp->stamp($xml);
 * } catch (\Finkok\CfdiBundle\Exception\FinkokExceptionInterface $e) {
 *     // cualquier fallo de transporte, SOAP, configuración o validación
 * }
 * ```
 */
interface FinkokExceptionInterface extends \Throwable
{
}
