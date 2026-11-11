<?php

/*
 * Gives phpstan-doctrine a real EntityManager so it can validate DQL / QueryBuilder
 * queries and check entity property types against their column mappings.
 * Boots the same kernel as the functional API tests on the MariaDB platform; no database connection is opened,
 * so no server needs to be running.
 */

use Greendot\EshopBundle\Tests\App\ApiTestKernel;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (getenv('TEST_DATABASE_URL') === false && !isset($_SERVER['TEST_DATABASE_URL'])) {
    $_ENV['TEST_DATABASE_URL'] = $_SERVER['TEST_DATABASE_URL'] = ApiTestKernel::DEFAULT_DATABASE_URL;
}

$kernel = new ApiTestKernel('phpstan', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
