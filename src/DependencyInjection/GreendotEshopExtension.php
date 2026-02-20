<?php

namespace Greendot\EshopBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class GreendotEshopExtension extends Extension implements PrependExtensionInterface
{


    public function load(array $configs, ContainerBuilder $container)
    {

    }

    public function prepend(ContainerBuilder $container)
    {
        $bundleTemplatesDir = dirname(__DIR__, 2).'/templates';

        $container->prependExtensionConfig('twig', [
            'paths' => [
                $bundleTemplatesDir => 'GreendotEshopBundle'
            ]
        ]);
    }
}