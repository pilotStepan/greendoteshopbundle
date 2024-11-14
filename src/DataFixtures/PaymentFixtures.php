<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\PaymentAction;
use Greendot\EshopBundle\Factory\Project\PaymentFactory;
use Greendot\EshopBundle\Factory\Project\PurchaseFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class PaymentFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }
    public function load(ObjectManager $manager): void
    {
        $purchases = PurchaseFactory::all();
        $paymentAction = $this->entityManager->getRepository(PaymentAction::class)->findOneBy(['name' => 'platba převodem']);

        foreach ($purchases as $purchase) {
            $payment = PaymentFactory::createOne([
                'externalId' => $purchase->getId(),
                'purchase' => $purchase,
                'date' => $purchase->getDateInvoiced(),
                'action' => $paymentAction,
            ]);
        }
    }

    public function getDependencies(): array
    {
        return [
            PurchaseFixtures::class,
            PaymentActionFixtures::class,
        ];
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}
