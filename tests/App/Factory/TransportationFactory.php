<?php

namespace Greendot\EshopBundle\Tests\App\Factory;

use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\TransportationAction;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Transportation>
 */
final class TransportationFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Transportation::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->words(2, true),
            'description' => self::faker()->sentence(),
            'descriptionMail' => self::faker()->sentence(),
            'descriptionDuration' => 2,
            'html' => '<p>shipping</p>',
            'icon' => 'truck.svg',
            'duration' => 2,
            'squence' => 1,
            'country' => 'CZ',
            'stateUrl' => 'https://example.test/track',
            'isEnabled' => true,
            'transportationAction' => TransportationAction::DELIVERY,
        ];
    }
}
