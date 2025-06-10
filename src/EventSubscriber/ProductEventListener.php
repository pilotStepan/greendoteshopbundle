<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\ProductInfoGetter;
use Symfony\Component\HttpFoundation\RequestStack;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: Product::class)]
class ProductEventListener
{

    public function __construct(
        private RequestStack       $requestStack,
        private CurrencyRepository $currencyRepository,
        private ProductRepository  $productRepository,
        private ProductInfoGetter $productInfoGetter,
    ) {}

    public function postLoad(Product $product, PostLoadEventArgs $args): void
    {
        $entity = $product;

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

            // TODO: fix somehow
            // get calculated prices with lowest priceNoVat from among variants
            $variants = $entity->getProductVariants();
            $lowestCalculatedPrices = null;
            foreach ($variants as $variant){
                /*if ($lowestCalculatedPrices === null || $variant->getCalculatedPrices()['priceNoVat'] < $lowestCalculatedPrices['priceNoVat']){
                    $lowestCalculatedPrices=$variant->getCalculatedPrices();
                }*/
            }

            $currencySymbol = $currency->getSymbol();
            $availability = $this->productRepository->findAvailabilityByProduct($entity);
            $parameters = $this->productRepository->calculateParameters($entity);
            $priceString    = $this->productInfoGetter->getProductPriceString($entity, $currency);


            $entity->setPriceFrom($priceString); // $lowestCalculatedPrices['priceNoVat']
            $entity->setCurrencySymbol($currencySymbol);
            $entity->setAvailability($availability);
            $entity->setParameters($parameters);
            $entity->setCalculatedPrices($lowestCalculatedPrices);
        }
    }
}
