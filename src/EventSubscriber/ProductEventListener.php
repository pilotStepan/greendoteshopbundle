<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Service\SessionService;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;

#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: Product::class)]
class ProductEventListener
{

    public function __construct(
        private readonly ProductRepository       $productRepository,
        private readonly CalculatedPricesService $calculatedPricesService,
        private readonly SessionService          $sessionService,
    ) {}

    public function postLoad(Product $product, PostLoadEventArgs $args): void
    {
        $currencySymbol = $this->sessionService->getCurrency(true);;
        $availability = $this->productRepository->findAvailabilityByProduct($product);
        $parameters = $this->productRepository->calculateParameters($product);

        $this->calculatedPricesService->makeCalculatedPricesForProduct($product);
//        if (!isset($product->getCalculatedPrices()['priceNoVat'])) {
//            dd($product);
//        }
        $product->setPriceFrom($product->getCalculatedPrices()['priceNoVat']); // $lowestCalculatedPrices['priceNoVat']
        $product->setCurrencySymbol($currencySymbol);
        $product->setAvailability($availability);
        $product->setParameters($parameters);
    }
}
