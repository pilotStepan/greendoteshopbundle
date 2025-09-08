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
                ->arrayNode('blog')
                    ->children()
                        ->stringNode('slug')->defaultValue('blog')->end()
                        ->booleanNode('has_landing')->defaultValue(true)->end()
                    ->end()
                ->end()
//                ->arrayNode('transportation')
//                    ->children()
//                        ->arrayNode('dpd')
//                            ->addDefaultsIfNotSet()
//                            ->children()
//                                ->integerNode('sender_address_id')->defaultValue(0)->end()
//                                ->integerNode('zip_code')->defaultValue(0)->end()
//                                ->stringNode('street')->defaultValue('street')->end()
//                                ->stringNode('name')->defaultValue('name')->end()
//                                ->stringNode('country_code')->defaultValue('CZ')->end()
//                                ->integerNode('contact_phone')->defaultValue(132456789)->end()
//                                ->stringNode('contact_phone_prefix')->defaultValue('+420')->end()
//                                ->stringNode('contact_name')->defaultValue('name')->end()
//                                ->stringNode('contact_email')->defaultValue('podpora@greendot.cz')->end()
//                                ->stringNode('company_name')->defaultValue('company')->end()
//                                ->stringNode('city')->defaultValue('Praha')->end()
//                            ->end()
//                        ->end()
//                    ->end()
//                ->end()
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
            'greendot_eshop.blog.has_landing',
            $config['blog']['has_landing']
        );
        $builder->setParameter(
            'greendot_eshop.blog.slug',
            $config['blog']['slug']
        );
//        $builder->setParameter(
//            'greendot_eshop.transportation.dpd.sender_data',
//            $config['transportation']['dpd'] ?? []
//        );
    }
}