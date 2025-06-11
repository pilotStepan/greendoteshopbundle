<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;
use Greendot\EshopBundle\Service\ProductInfoGetter;
use Greendot\EshopBundle\Service\SessionService;
use Symfony\Component\HttpFoundation\RequestStack;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

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
        $product->setPriceFrom($product->getCalculatedPrices()['priceNoVat']); // $lowestCalculatedPrices['priceNoVat']
        $product->setCurrencySymbol($currencySymbol);
        $product->setAvailability($availability);
        $product->setParameters($parameters);
    }
}
