<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Service\SessionService;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Doctrine\Common\EventSubscriber;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;

class ProductEventSubscriber implements EventSubscriber
{

    public function __construct(
        private readonly ProductRepository       $productRepository,
        private readonly CalculatedPricesService $calculatedPricesService,
        private readonly SessionService          $sessionService,
    ) {}

  public function getSubscribedEvents(): array
    {
        return [Events::postLoad];
    }


    public function postLoad(PostLoadEventArgs $args): void
    {
        $product = $args->getObject();

        if (!$product instanceof Product) {
            return; // only handle Product entities
        }

        $currencySymbol = $this->sessionService->getCurrency(true);;
        $availability = $this->productRepository->findAvailabilityByProduct($product);
        $parameters = $this->productRepository->calculateParameters($product);

        // if it doesn't have main upload, it tries to substitute it
        if ($product->getUpload() === null) {
            $productUploads = [];
            foreach($product->getProductUploadGroups() as $productUploadGroup) {
                foreach($productUploadGroup->getUploadGroup()->getUpload() as $upload)
                {
                    $productUploads[] = $upload;
                }
            }
            if(count($productUploads) === 0)
            {
                foreach ($product->getProductVariants() as $productVariant) {
                    foreach($productVariant->getProductVariantUploadGroups() as $productVariantUploadGroup) {
                        foreach($productVariantUploadGroup->getUploadGroup()->getUpload() as $upload)
                        {
                            $productUploads[] = $upload;
                        }
                    }
                }
            }
            if (count($productUploads) > 0) {
                usort($productUploads, function($a, $b) {
                    return $a->getSequence() <=> $b->getSequence();
                });
                $product->setUpload($productUploads[0]);
            }
        
        }



        $this->calculatedPricesService->makeCalculatedPricesForProduct($product);
        if (!isset($product->getCalculatedPrices()['priceNoVat'])) {
            // dd($product);
            $product->setPriceFrom(0);
        }
        else
        {
            $product->setPriceFrom($product->getCalculatedPrices()['priceNoVat']); // $lowestCalculatedPrices['priceNoVat']
        }
       
        $product->setCurrencySymbol($currencySymbol);
        $product->setAvailability($availability);
        $product->setParameters($parameters);
    }
}
