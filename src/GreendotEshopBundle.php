<?php

namespace Greendot\EshopBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class GreendotEshopBundle extends AbstractBundle
{

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('global')
                    ->children()
                        ->stringNode('absolute_url')->defaultValue('https://www.example.com')->end()
                    ->end()
                ->end()
                ->arrayNode('price')
                    ->children()
                        ->arrayNode('free_from_price')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('includes_vat')->defaultValue(false)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

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

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // load an XML, PHP or YAML file
        $container->import('../config/services.yaml');


        $absoluteUrl = $config['global']['absolute_url'] ?? 'https://www.example.com';
        $builder->setParameter(
            'greendot_eshop.global.absolute_url',
            $absoluteUrl
        );
        $builder->setParameter(
            'greendot_eshop.price.free_from_price.includes_vat',
            $config['price']['free_from_price']['includes_vat'] ?? false
        );
    }
}