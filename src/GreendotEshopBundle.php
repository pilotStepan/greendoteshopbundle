<?php

namespace Greendot\EshopBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GreendotEshopBundle extends AbstractBundle
{

    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        /*
         * To extend the bundle to work with mongoDB and couchDB you can follow this tutorial
         * http://symfony.com/doc/current/doctrine/mapping_model_classes.html
         * */


        parent::build($container);
        $ormCompilerClass = 'Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass';

        if (class_exists($ormCompilerClass))
        {

            $namespaces = ['GreendotEshopBundle',];
            $directories = [
                realpath(__DIR__.'/Entity'),
            ];
            $managerParameters = array();
            $enabledParameter = false;
            $aliasMap = [];
            $container->addCompilerPass(
                DoctrineOrmMappingsPass::createAttributeMappingDriver(
                    $namespaces,
                    $directories,
                    $managerParameters,
                    $enabledParameter,
                    $aliasMap,
                    true,
                )
            );
        }


    }
}