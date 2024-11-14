<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\ClientAddressFactory;
use Greendot\EshopBundle\Factory\Project\ClientFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ClientFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $clients = ClientFactory::createMany(rand(1, 5));

        foreach ($clients as $client) {
            ClientAddressFactory::createOne(['client' => $client]);
        }
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}
