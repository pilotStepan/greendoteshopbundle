<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Enum\UploadGroupTypeEnum;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Greendot\EshopBundle\Service\ListenerManager;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;

#[AsEntityListener(event: Events::postLoad, priority: 10, method: 'postLoad', entity: Product::class)]
class ProductEventListener
{

    public function __construct(
        private ProductRepository       $productRepository,
        private CalculatedPricesService $calculatedPricesService,
        private CurrencyManager         $currencyManager,
        private ListenerManager         $listenerManager,
    ) {}

    public function postLoad(Product $product, PostLoadEventArgs $args): void
    {

        if (!$this->supports()) {
            return;
        }

        $currencySymbol = $this->currencyManager->get()->getSymbol();
        $availability = $this->productRepository->findAvailabilityByProduct($product);
        $parameters = $this->productRepository->calculateParameters($product);

        // if it doesn't have main upload, it tries to substitute it
        if ($product->getUpload() === null) {

            $productUploads = [];
            foreach($product->getProductUploadGroups() as $productUploadGroup) {
                if ($productUploadGroup->getUploadGroup()->getType() != UploadGroupTypeEnum::IMAGE){
                    continue;
                }
                foreach($productUploadGroup->getUploadGroup()->getUpload() as $upload)
                {
                    $productUploads[] = $upload;
                }
            }
            if(count($productUploads) === 0)
            {
                foreach ($product->getProductVariants() as $productVariant) {
                    foreach($productVariant->getProductVariantUploadGroups() as $productVariantUploadGroup) {
                        if ($productVariantUploadGroup->getUploadGroup()->getType() != UploadGroupTypeEnum::IMAGE){
                            continue;
                        }
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
                $productUploads[0]->setIsDynamicallySet(true);
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

    public function supports() : bool
    {
        return !$this->listenerManager->isDisabled(self::class);
    }
}