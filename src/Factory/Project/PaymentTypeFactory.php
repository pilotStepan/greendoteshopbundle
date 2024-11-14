<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<PaymentType>
 *
 * @method        PaymentType|Proxy                     create(array|callable $attributes = [])
 * @method static PaymentType|Proxy                     createOne(array $attributes = [])
 * @method static PaymentType|Proxy                     find(object|array|mixed $criteria)
 * @method static PaymentType|Proxy                     findOrCreate(array $attributes)
 * @method static PaymentType|Proxy                     first(string $sortedField = 'id')
 * @method static PaymentType|Proxy                     last(string $sortedField = 'id')
 * @method static PaymentType|Proxy                     random(array $attributes = [])
 * @method static PaymentType|Proxy                     randomOrCreate(array $attributes = [])
 * @method static PaymentTypeRepository|RepositoryProxy repository()
 * @method static PaymentType[]|Proxy[]                 all()
 * @method static PaymentType[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static PaymentType[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static PaymentType[]|Proxy[]                 findBy(array $attributes)
 * @method static PaymentType[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static PaymentType[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class PaymentTypeFactory extends ModelFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function getDefaults(): array
    {
        $name = self::faker()->word();
        return [
            'Name' => $name,
            'country' => 'CZ',
            'description' => $name,
            'description_duration' => $name,
            'descrition_mail' => $name,
            'html' => $name,
            'duration' => 15,
            'free_from_price' => rand(0, 5) * 500,
            'icon' => "$name.svg",
            'price' => rand(0, 5) * 10,
            'sequence' => 1,
            'isEnabled' => 1,
            'vat' => 21,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this// ->afterInstantiate(function(PaymentType $paymentType): void {})
            ;
    }

    protected static function getClass(): string
    {
        return PaymentType::class;
    }
}
