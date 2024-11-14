<?php

namespace Greendot\EshopBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ObjectManager;
use Doctrine\DBAL\Connection;

class AppFixtures extends Fixture
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        ini_set('memory_limit', '256M');
        $this->connection = $connection;
    }


    public function load(ObjectManager $manager): void
    {

    }

    /**
     * @throws Exception
     */
    private function resetAutoIncrement(string $tableName): void
    {
        $this->connection->executeStatement("ALTER TABLE $tableName AUTO_INCREMENT = 1;");
    }

}
