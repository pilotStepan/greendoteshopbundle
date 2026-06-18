<?php

namespace Greendot\EshopBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Yaml\Yaml;

class GreendotEshopBundle extends AbstractBundle
{

    /**
     * Uses symfony-native prependExtension
     * Configured to look into config/extension/*.yaml and prepend those extensions
     * filename doesn't matter (but you should still keep the convention... )
     * What matters is the root key in yaml files which MUST specify the extension name (eg: doctrine, services) and valid config
     *
     * @param ContainerConfigurator $container
     * @param ContainerBuilder $builder
     * @return void
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $configDir = __DIR__. '/../config/extensions';
        foreach (glob($configDir. '/*.yaml') as $file) {
            $config = Yaml::parseFile($file);
            foreach ($config as $extension => $values){
                if (!$builder->hasExtension($extension)) continue;
                $builder->prependExtensionConfig($extension, $values);
            }
        }
        parent::prependExtension($container, $builder);
    }


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
                        ->integerNode('items_per_page')->defaultValue(10)->end()
                        ->booleanNode('has_landing')->defaultValue(true)->end()
                    ->end()
                ->end()
                ->arrayNode('mail')
                    ->children()
                        ->arrayNode('order')
                            ->children()
                                ->booleanNode('send_proforma')->defaultValue(true)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('payment')
                    ->children()
                        ->arrayNode('rb_bank')
                            ->children()
                                ->booleanNode('enabled')->defaultValue(false)->end()
                                ->stringNode('shopname')->defaultValue('')->end()
                                ->stringNode('account')->defaultValue('')->end()
                                ->stringNode('bank_code')->defaultValue('')->end()
                                ->stringNode('password')->defaultValue('')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('shop')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->stringNode('default_vat_type')
                            ->defaultValue(VatCalculationType::WithVAT->value)
                            ->validate()
                                ->ifNotInArray(array_column(VatCalculationType::cases(), 'value'))
                                ->thenInvalid('Invalid VAT type "%s"')
                            ->end()
                        ->end()
                        ->stringNode('default_discount_type')
                            ->defaultValue(DiscountCalculationType::WithDiscount->value)
                            ->validate()
                                ->ifNotInArray(array_column(DiscountCalculationType::cases(), 'value'))
                                ->thenInvalid('Invalid VAT type "%s"')
                            ->end()
                        ->end()
                        ->stringNode('secondary_currency_name')->defaultValue('EUR')->end()
                        ->arrayNode('price')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('extension')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->stringNode('discount_combination_strategy')
                                            ->defaultValue('sum')
                                        ->end()
                                    ->end()
                                ->end()
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

        if (class_exists($ormCompilerClass)) {

            $namespaces = ['GreendotEshopBundle',];
            $directories = [
                realpath(__DIR__ . '/Entity'),
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
        $builder->setParameter(
            'greendot_eshop.blog.items_per_page',
            $config['blog']['items_per_page']
        );
        $builder->setParameter(
            'greendot_eshop.shop.default_vat_type',
            $config['shop']['default_vat_type']
        );
        $builder->setParameter(
            'greendot_eshop.shop.default_discount_type',
            $config['shop']['default_discount_type']
        );

        $sendProforma = $config['mail']['order']['send_proforma'] ?? true;
        $builder->setParameter(
            'greendot_eshop.mail.order.send_proforma',
            $sendProforma
        );

        $rbBankConfig = $config['payment']['rb_bank'] ?? [];
        $builder->setParameter('greendot_eshop.payment.rb_bank.enabled', $rbBankConfig['enabled'] ?? false);
        $builder->setParameter('greendot_eshop.payment.rb_bank.shopname', $rbBankConfig['shopname'] ?? '');
        $builder->setParameter('greendot_eshop.payment.rb_bank.account', $rbBankConfig['account'] ?? '');
        $builder->setParameter('greendot_eshop.payment.rb_bank.bank_code', $rbBankConfig['bank_code'] ?? '');
        $builder->setParameter('greendot_eshop.payment.rb_bank.password', $rbBankConfig['password'] ?? '');

        $secondaryCurrencyName = $config['shop']['secondary_currency_name'] ?? 'EUR';
        $builder->setParameter(
            'greendot_eshop.shop.secondary_currency_name',
            $secondaryCurrencyName
        );

        $builder->setParameter(
            'greendot_eshop.shop.price.extension.discount_combination_strategy',
            $config['shop']['price']['extension']['discount_combination_strategy'],
        );

//        $builder->setParameter(
//            'greendot_eshop.transportation.dpd.sender_data',
//            $config['transportation']['dpd'] ?? []
//        );
    }
}