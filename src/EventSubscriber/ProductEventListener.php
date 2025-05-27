<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\ProductInfoGetter;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductEventListener
{
    public function __construct(
        private ProductInfoGetter  $productInfoGetter,
        private RequestStack       $requestStack,
        private CurrencyRepository $currencyRepository
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
            /*
             * TODO remove the strings and change to array
             */
            $priceString    = $this->productInfoGetter->getProductPriceString($entity, $currency);

            $entity->setPriceFrom($priceString);
            $entity->setCurrencySymbol($currencySymbol);
        }
    }
}
