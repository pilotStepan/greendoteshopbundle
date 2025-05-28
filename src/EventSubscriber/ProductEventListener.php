<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\ProductInfoGetter;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductEventListener
{
    public function __construct(
        private ProductInfoGetter  $productInfoGetter,
        private RequestStack       $requestStack,
        private CurrencyRepository $currencyRepository,
        private ProductRepository  $productRepository,
    ) {}

    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Product) {

            $request  = $this->requestStack->getCurrentRequest();
            $currency = false;

            if ($request) {
                $session  = $request->getSession();
                $currency = $session->get('selectedCurrency');
            }

            if (!$currency) {
                $currency = $this->currencyRepository->findOneBy(['isDefault' => 1]);
            }

            $currencySymbol = $currency->getSymbol();
            $priceString    = $this->productInfoGetter->getProductPriceString($entity, $currency);
            $availability = $this->productRepository->findAvailabilityByProduct($entity);
            $parameters = $this->productRepository->calculateParameters($entity);

            $entity->setPriceFrom($priceString);
            $entity->setCurrencySymbol($currencySymbol);
            $entity->setAvailability($availability);
            $entity->setParameters($parameters);
        }
    }
}
