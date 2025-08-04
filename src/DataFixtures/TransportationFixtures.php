<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Enum\TransportationAction;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class TransportationFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private array $dataArray = [
        1 => [
            'name' => 'Praha 7, Holešovice',
            'description' => 'Zboží si vyzvednete v kamenné prodejně Yogashopu - Jateční 1615/45, Praha 7 - Holešovice. Budeme Vás kontaktovat SMS a e-mailem až bude vaše objednávka připravena k vyzvednutí.',
            'icon' => 'logo.png',
            'transportation_action' => TransportationAction::PICKUP,
        ],
        2 => [
            'name' => 'Balíkovna',
            'description' => 'Mapa balíkovny',
            'icon' => 'balikovna-logo.png',
            'transportation_action' => TransportationAction::BOX,
        ],
        3 => [
            'name' => 'Zásilkovna',
            'description' => 'Mapa zásilkovny',
            'icon' => 'kosik-zasilkovna.png',
            'transportation_action' => TransportationAction::BOX,
        ],
        4 => [
            'name' => 'Balík na poštu',
            'description' => 'Mapa pošty',
            'icon' => 'kosik-cp-na-postu.png',
            'transportation_action' => TransportationAction::BOX,
        ],
        5 => [
            'name' => 'Balík do ruky - Česká Republika',
            'description' => 'Balík do ruky - Česká Republika',
            'icon' => 'kosik-cp-do-ruky.png',
            'transportation_action' => TransportationAction::DELIVERY,
        ],
        6 => [
            'name' => 'DPD - Česká republika',
            'description' => 'Kurýr společnost DPD Vám zboží obvykle doručí následující den od podání zísilky. Balíky jsou rozváženy v pracovní dny od 8 do 18 hodin. V den expedice zásilky Vám bude e-mailem a SMS odeslána informace o čísle balíku. Buďte, prosím, připraveni na dodací adrese převzít zboží, přepravce nemůže čekat.',
            'icon' => 'kosik-dpd.png',
            'transportation_action' => TransportationAction::COURIER,
        ],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }
    public function load(ObjectManager $manager): void
    {
        $paymentTypes = $this->entityManager->getRepository(PaymentType::class)->findAll();
        foreach ($this->dataArray as $id => $data) {
            $object = new Transportation();
            $object->setName($data['name']);
            $object->setDescription($data['description']);
            $object->setIcon($data['icon']);
            $object->setDescriptionMail('');
            $object->setDescriptionDuration(0);
            $object->setHtml('');
            $object->setDuration(0);
            $object->setSquence(0);
            $object->setCountry('');
            $object->setStateUrl('');
            $object->setTransportationAction($data['transportation_action']);
            foreach ($paymentTypes as $paymentType) $object->addPaymentType($paymentType);
            $manager->persist($object);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            PaymentTypeFixtures::class,
        ];
    }
    public static function getGroups(): array
    {
        return ['static'];
    }
}