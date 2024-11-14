<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\ClientAddressFactory;
use Greendot\EshopBundle\Factory\Project\ClientFactory;
use Greendot\EshopBundle\Factory\Project\ProductFactory;
use Greendot\EshopBundle\Factory\Project\PurchaseFactory;
use Greendot\EshopBundle\Factory\Project\TransportationFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PurchaseFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $clients = ClientFactory::all();

        foreach ($clients as $client) {
            $purchases = PurchaseFactory::createMany(rand(1,3), [
                'client' => $client,
                'clientAddress' => $client->getClientAddresses()->first(),
            ]);
        }
//        $purchases = PurchaseFactory::createMany(30);
    }

    public function getDependencies(): array
    {
        return [
            PaymentTypeFixtures::class,
            TransportationFixtures::class,
        ];
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}
