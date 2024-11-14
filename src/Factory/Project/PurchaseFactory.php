<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Purchase>
 *
 * @method        Purchase|Proxy                     create(array|callable $attributes = [])
 * @method static Purchase|Proxy                     createOne(array $attributes = [])
 * @method static Purchase|Proxy                     find(object|array|mixed $criteria)
 * @method static Purchase|Proxy                     findOrCreate(array $attributes)
 * @method static Purchase|Proxy                     first(string $sortedField = 'id')
 * @method static Purchase|Proxy                     last(string $sortedField = 'id')
 * @method static Purchase|Proxy                     random(array $attributes = [])
 * @method static Purchase|Proxy                     randomOrCreate(array $attributes = [])
 * @method static PurchaseRepository|RepositoryProxy repository()
 * @method static Purchase[]|Proxy[]                 all()
 * @method static Purchase[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static Purchase[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static Purchase[]|Proxy[]                 findBy(array $attributes)
 * @method static Purchase[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Purchase[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class PurchaseFactory extends ModelFactory
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
        $dateIssue = (new \DateTime())->modify("-" . rand(25, 72) . " hours");
        $dateInvoiced = $dateIssue->modify("+" . rand(1, 24) . " hours");
        return [
            'date_issue' => $dateIssue,
            'date_invoiced' => $dateInvoiced,
            'invoiceNumber' => $dateInvoiced->format('dmY') . rand(1000, 9999),
            'paymentType' => PaymentTypeFactory::random(),
            'transportation' => TransportationFactory::random(),
            'state' => 'paid',
            'transportNumber' => rand(1000, 9999),
            'clientNumber' => self::faker()->randomNumber(),
            'name' => self::faker()->word(),
//            'invoice_number' => $dateInvoiced->format('dmY') . rand(1000, 9999),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Purchase $purchase): void {})
            ;
    }

    protected static function getClass(): string
    {
        return Purchase::class;
    }
}
