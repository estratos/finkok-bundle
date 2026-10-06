<?php

declare(strict_types=1);

namespace Estratos\FinkokBundle;

use Estratos\FinkokBundle\DependencyInjection\Compiler\WiringPass;
use Estratos\FinkokBundle\DependencyInjection\FinkokExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle de Symfony para consumir los Web Services SOAP de Finkok.
 *
 * Servicios expuestos (autowireables por su interfaz):
 *  - {@see \Estratos\FinkokBundle\Contract\StampServiceInterface}  timbrado
 *  - {@see \Estratos\FinkokBundle\Contract\CancelServiceInterface} cancelación
 *  - {@see \Estratos\FinkokBundle\Config\CredentialsProviderInterface} perfiles multi-emisor
 *  - {@see \Estratos\FinkokBundle\Soap\SoapTransportInterface} transporte SOAP
 *  - {@see \Estratos\FinkokBundle\Csd\CsdEncoderInterface} codificación de CSD
 */
final class FinkokBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new WiringPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= new FinkokExtension();
    }
}
