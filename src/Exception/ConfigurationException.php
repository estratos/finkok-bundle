<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle\Exception;

/**
 * La configuración del bundle (o de un perfil de credenciales) es inválida o
 * está incompleta.
 *
 * No es `final` para permitir especializaciones como
 * {@see ProfileNotFoundException}.
 */
class ConfigurationException extends \InvalidArgumentException implements FinkokExceptionInterface
{
}
