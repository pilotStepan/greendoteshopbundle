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

        return new ApiTestKernel('test', true);
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $dbPath = self::dbPath();
        if (!is_dir(dirname($dbPath))) {
            mkdir(dirname($dbPath), 0777, true);
        }
        if (is_file($dbPath)) {
            unlink($dbPath);
        }

        $kernel = self::bootKernel();
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadata);

        self::ensureKernelShutdown();
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
        $this->getEntityManager()->beginTransaction();

        // Every request runs LocaleAwareListenerDecorator -> CurrencyManager, which requires
        // a default Currency row to exist; without one, even unrelated endpoints 500.
        CurrencyFactory::createOne();
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
