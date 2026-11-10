<?php

namespace Greendot\EshopBundle\Tests\App;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Greendot\EshopBundle\Tests\App\Factory\CurrencyFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Zenstruck\Foundry\Test\Factories;

abstract class ApiTestCase extends WebTestCase
{
    use Factories;

    protected KernelBrowser $client;

    protected static function createKernel(array $options = []): KernelInterface
    {
        $_ENV['API_TEST_DB_PATH'] = $_SERVER['API_TEST_DB_PATH'] = self::dbPath();
        $_ENV['API_TEST_DATABASE_URL'] = $_SERVER['API_TEST_DATABASE_URL'] = self::externalDatabaseUrl() ?? 'sqlite:///' . self::dbPath();

        return new ApiTestKernel('test', true);
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $usesExternalDb = self::externalDatabaseUrl() !== null;
        if (!$usesExternalDb) {
            $dbPath = self::dbPath();
            if (!is_dir(dirname($dbPath))) {
                mkdir(dirname($dbPath), 0777, true);
            }
            if (is_file($dbPath)) {
                unlink($dbPath);
            }
        }

        $kernel = self::bootKernel();
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        if ($usesExternalDb) {
            // One shared database: start every test class from an empty schema.
            $schemaTool->dropDatabase();
        }
        $schemaTool->createSchema($metadata);

        self::ensureKernelShutdown();
    }

    /**
     * TEST_DATABASE_URL (e.g. mysql://root:root@127.0.0.1:3306/eshop_test?serverVersion=10.5.29-MariaDB&charset=utf8mb4)
     * runs the functional tests against a real MariaDB instead of the default per-class SQLite file.
     * CI sets it; production runs MariaDB, so SQL that SQLite accepts but MariaDB rejects (or vice versa)
     * is only caught this way.
     */
    /** For tests of MariaDB-specific SQL (locking, JSON functions, ...) that SQLite can't run. */
    protected static function requiresMariaDb(): void
    {
        if (self::externalDatabaseUrl() === null) {
            self::markTestSkipped('Needs MariaDB: set TEST_DATABASE_URL (CI always does).');
        }
    }

    private static function externalDatabaseUrl(): ?string
    {
        $url = $_SERVER['TEST_DATABASE_URL'] ?? $_ENV['TEST_DATABASE_URL'] ?? getenv('TEST_DATABASE_URL');

        return \is_string($url) && $url !== '' ? $url : null;
    }

    private static function dbPath(): string
    {
        // One file per test class (rather than a single shared one) avoids Windows file-lock
        // contention between classes and means each class always starts from a clean schema.
        $name = str_replace('\\', '_', static::class);

        return dirname(__DIR__, 2) . '/var/test/api_test_' . $name . '.db';
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        if ($this->rollsBackAfterEachTest()) {
            $this->getEntityManager()->beginTransaction();
        }

        // Every request runs CurrencyLocaleListener -> CurrencyManager, which requires
        // a default Currency row to exist; without one, even unrelated endpoints 500.
        CurrencyFactory::createOne();
    }

    /**
     * Each test runs in a transaction rolled back in tearDown(). Tests that run DDL return false:
     * MariaDB commits DDL implicitly, so there is nothing left to roll back. The schema is recreated
     * per test class, so the next class still starts clean.
     */
    protected function rollsBackAfterEachTest(): bool
    {
        return true;
    }

    protected function tearDown(): void
    {
        $em = $this->getEntityManager();
        if ($em->getConnection()->isTransactionActive()) {
            $em->getConnection()->rollBack();
        }
        parent::tearDown();
    }
}
