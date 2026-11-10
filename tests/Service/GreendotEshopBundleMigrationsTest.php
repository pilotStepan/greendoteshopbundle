<?php

namespace Greendot\EshopBundle\Tests\Service;

use Doctrine\Bundle\MigrationsBundle\DependencyInjection\DoctrineMigrationsExtension;
use Greendot\EshopBundle\GreendotEshopBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

/**
 * Tenants run the bundle's migrations without any config of their own: the bundle adds its migrations
 * directory to doctrine_migrations.migrations_paths, next to the tenant's own path.
 */
final class GreendotEshopBundleMigrationsTest extends TestCase
{
    public function testBundleAddsItsMigrationsNextToTheTenantsOwn(): void
    {
        $builder = $this->containerBuilder();
        $builder->registerExtension(new DoctrineMigrationsExtension());
        // What every tenant has in config/packages/doctrine_migrations.yaml.
        $builder->loadFromExtension('doctrine_migrations', [
            'migrations_paths' => ['DoctrineMigrations' => '%kernel.project_dir%/migrations'],
        ]);

        $this->prepend($builder);

        $configs = $builder->getExtensionConfig('doctrine_migrations');
        $builder->getExtension('doctrine_migrations')->load($configs, $builder);

        $paths = $builder->getDefinition('doctrine.migrations.configuration')->getMethodCalls();
        $registered = [];
        foreach ($paths as [$method, $arguments]) {
            if ($method === 'addMigrationsDirectory') {
                $registered[$arguments[0]] = $arguments[1];
            }
        }

        self::assertSame([
            GreendotEshopBundle::MIGRATIONS_NAMESPACE => GreendotEshopBundle::migrationsDirectory(),
            'DoctrineMigrations' => '%kernel.project_dir%/migrations',
        ], $registered);
        self::assertDirectoryExists(GreendotEshopBundle::migrationsDirectory());
    }

    public function testNothingIsAddedWithoutDoctrineMigrationsBundle(): void
    {
        $builder = $this->containerBuilder();

        $this->prepend($builder);

        self::assertSame([], $builder->getExtensionConfig('doctrine_migrations'));
    }

    private function containerBuilder(): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());
        $builder->setParameter('kernel.bundles', []);
        $builder->setParameter('kernel.bundles_metadata', []);

        return $builder;
    }

    private function prepend(ContainerBuilder $builder): void
    {
        $extension = (new GreendotEshopBundle())->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);
    }
}
