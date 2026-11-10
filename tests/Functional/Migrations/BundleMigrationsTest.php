<?php

namespace Greendot\EshopBundle\Tests\Functional\Migrations;

use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Greendot\EshopBundle\GreendotEshopBundle;
use Greendot\EshopBundle\Tests\App\ApiTestCase;

/**
 * Runs every bundle migration with the real Doctrine Migrator on MariaDB, on top of the schema the current
 * mapping creates, then forgets they ran and runs them again. Tenant databases drift and some already got a
 * change from their own migration or by hand, so a bundle migration must succeed on a database that already
 * has its change.
 */
final class BundleMigrationsTest extends ApiTestCase
{
    protected function rollsBackAfterEachTest(): bool
    {
        return false;
    }

    public function testEveryMigrationRunsTwiceOnTheCurrentSchema(): void
    {
        self::requiresMariaDb();

        $migrations = glob(GreendotEshopBundle::migrationsDirectory() . '/Version*.php');
        if ($migrations === false || $migrations === []) {
            self::markTestSkipped('The bundle has no migrations yet.');
        }

        foreach (['first run', 'second run, migrations already applied but not recorded'] as $run) {
            // A fresh factory per run: an executed migration instance is frozen and can't run again.
            $factory = DependencyFactory::fromConnection(
                new ConfigurationArray([
                    'migrations_paths' => [GreendotEshopBundle::MIGRATIONS_NAMESPACE => GreendotEshopBundle::migrationsDirectory()],
                ]),
                new ExistingConnection($this->getEntityManager()->getConnection()),
            );
            $storage = $factory->getMetadataStorage();
            $storage->ensureInitialized();

            $plan = $factory->getMigrationPlanCalculator()->getPlanUntilVersion(
                $factory->getVersionAliasResolver()->resolveVersionAlias('latest'),
            );
            self::assertCount(\count($migrations), $plan, $run);

            $factory->getMigrator()->migrate($plan, new MigratorConfiguration());

            self::assertCount(\count($migrations), $storage->getExecutedMigrations()->getItems(), $run);
            $storage->reset();
        }
    }
}
