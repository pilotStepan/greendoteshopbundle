<?php

namespace Greendot\EshopBundle\Tests\App;

use Doctrine\DBAL\Exception\ConnectionException;
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
        ApiTestKernel::databaseUrl();

        return new ApiTestKernel('test', true);
    }

    /**
     * Every test class starts from an empty schema in the MariaDB database at TEST_DATABASE_URL.
     * Production is MariaDB, and that is the only database the tests run on.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $kernel = self::bootKernel();
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        $schemaTool = new SchemaTool($em);
        try {
            $schemaTool->dropDatabase();
        } catch (ConnectionException $e) {
            throw new \RuntimeException(sprintf(
                'No test database at %s. Run the tests with `composer test`, which starts it (Docker), or set TEST_DATABASE_URL.',
                preg_replace('#//[^@/]*@#', '//***@', ApiTestKernel::databaseUrl()),
            ), 0, $e);
        }
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

        self::ensureKernelShutdown();
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
