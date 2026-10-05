<?php

declare(strict_types=1);

namespace Finkok\CfdiBundle;

use Finkok\CfdiBundle\DependencyInjection\Compiler\WiringPass;
use Finkok\CfdiBundle\DependencyInjection\FinkokExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle de Symfony para consumir los Web Services SOAP de Finkok.
 *
 * Servicios expuestos (autowireables por su interfaz):
 *  - {@see \Finkok\CfdiBundle\Contract\StampServiceInterface}  timbrado
 *  - {@see \Finkok\CfdiBundle\Contract\CancelServiceInterface} cancelación
 *  - {@see \Finkok\CfdiBundle\Config\CredentialsProviderInterface} perfiles multi-emisor
 *  - {@see \Finkok\CfdiBundle\Soap\SoapTransportInterface} transporte SOAP
 *  - {@see \Finkok\CfdiBundle\Csd\CsdEncoderInterface} codificación de CSD
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
