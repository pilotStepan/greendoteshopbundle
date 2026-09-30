<?php

namespace Greendot\EshopBundle\Tests\App\Factory;

use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<PaymentType>
 */
final class PaymentTypeFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PaymentType::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->words(2, true),
            'description' => self::faker()->sentence(),
            'descritionMail' => self::faker()->sentence(),
            'descriptionDuration' => '2',
            'html' => '<p>payment</p>',
            'icon' => 'card.svg',
            'duration' => 2,
            'sequence' => 1,
            'country' => 'CZ',
            'actionGroup' => PaymentTypeActionGroup::cases()[0],
        ];
    }
}
