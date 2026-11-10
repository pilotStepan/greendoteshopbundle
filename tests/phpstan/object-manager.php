<?php

/*
 * Gives phpstan-doctrine a real EntityManager so it can validate DQL / QueryBuilder
 * queries and check entity property types against their column mappings.
 * Boots the same kernel as the functional API tests; no database connection is opened.
 */

use Greendot\EshopBundle\Tests\App\ApiTestKernel;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$_ENV['API_TEST_DB_PATH'] = $_SERVER['API_TEST_DB_PATH'] = sys_get_temp_dir() . '/phpstan_eshopbundle.db';

$kernel = new ApiTestKernel('phpstan', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
