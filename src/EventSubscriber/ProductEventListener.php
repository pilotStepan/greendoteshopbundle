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

            // get calculated prices with lowest priceNoVat from among variants
            $variants = $entity->getProductVariants();
            $lowestCalculatedPrices = null;
            foreach ($variants as $variant){
                if ($lowestCalculatedPrices === null || $variant->getCalculatedPrices()['priceNoVat'] < $lowestCalculatedPrices['priceNoVat']){
                    $lowestCalculatedPrices=$variant->getCalculatedPrices();
                }
            }

            $currencySymbol = $currency->getSymbol();
            $availability = $this->productRepository->findAvailabilityByProduct($entity);
            $parameters = $this->productRepository->calculateParameters($entity);

            $entity->setPriceFrom($lowestCalculatedPrices['priceNoVat']);
            $entity->setCurrencySymbol($currencySymbol);
            $entity->setAvailability($availability);
            $entity->setParameters($parameters);
            $entity->setCalculatedPrices($lowestCalculatedPrices);
        }
    }
}
