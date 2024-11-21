<?php

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require 'vendor/autoload.php';

echo "Starting...\n";

ini_set('memory_limit', '512M');

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env.local');

$kernel = new \App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine.orm.entity_manager');

echo "Resetting database...\n";
$connection = $entityManager->getConnection();
$schemaManager = $connection->createSchemaManager();
$tables = $schemaManager->listTableNames();

$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tables as $table) {
    $connection->executeStatement('TRUNCATE TABLE ' . $table);
    $connection->executeStatement('ALTER TABLE ' . $table . ' AUTO_INCREMENT = 1');
}
$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');


$application = new Application($kernel);
$application->setAutoExit(false);

$input = new ArrayInput([
    'command' => 'doctrine:fixtures:load',
    '--no-interaction' => true,
]);

echo "Running Doctrine fixtures...\n";
$output = new ConsoleOutput();
$application->run($input, $output);

$kernel->shutdown();