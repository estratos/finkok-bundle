<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle\Exception;

/**
 * Excepción base de los errores en tiempo de ejecución del bundle.
 */
class FinkokException extends \RuntimeException implements FinkokExceptionInterface
{
}
