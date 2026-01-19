<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Greendot\EshopBundle\Enum\UploadGroupTypeEnum;
use Greendot\EshopBundle\Service\ListenerManager;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;

#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: ProductVariant::class)]
class ProductVariantEventListener
{
    public function __construct(
        private CalculatedPricesService $calculatedPricesService,
        private ListenerManager         $listenerManager,
    ) {}

    public function postLoad(ProductVariant $productVariant, PostLoadEventArgs $event): void
    { 
        if (!$this->supports())
        {
            return;
        }

        $this->calculatedPricesService->makeCalculatedPricesForProductVariant($productVariant);

        // if doesnt have upload try to substitue it
        
        if (!$productVariant->getUpload()) {
            if (
                $productVariant->getProduct()->getUpload() === null
                || $productVariant->getProduct()->getUpload()?->isDynamicallySet()
            ) 
            {
                foreach($productVariant->getProductVariantUploadGroups() as $productVariantUploadGroup) {
                    if ($productVariantUploadGroup->getUploadGroup()->getType() != UploadGroupTypeEnum::IMAGE){
                        continue;
                    }
                    foreach($productVariantUploadGroup->getUploadGroup()->getUpload() as $upload)
                    {
                        $upload->setIsDynamicallySet(true);
                        $productVariant->setUpload($upload);
                        break 2;
                    }
                }
            }
            else
            {
                $upload = $productVariant->getProduct()->getUpload();
                $upload->setIsDynamicallySet(true);
                $productVariant->setUpload($upload);
            }
        }
    }

    public function supports() : bool
    {
        return !$this->listenerManager->isDisabled(self::class);
    }
}
