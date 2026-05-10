<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Greendot\EshopBundle\Service\ListenerManager;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;

#[AsEntityListener(event: Events::loadClassMetadata, method: 'loadClassMetadata', entity: ProductVariant::class)]
class ProductVariantEventListener
{
    public function __construct(
        private CalculatedPricesService $calculatedPricesService,
        private ListenerManager         $listenerManager,
        private readonly ProductVariantRepository $productVariantRepository
    ) {}

    // TODO: move calculatedPrices creation to provider
    public function loadClassMetadata(ProductVariant $productVariant, LoadClassMetadataEventArgs $event): void
    {
        if (!$this->supports())
        {
            return;
        }

        // if doesnt have upload try to substitue it
        if (!$productVariant->getUpload()) {
            $upload = $this->productVariantRepository->findProductVariantUploadSubstitute($productVariant);
            if (!$upload) {
                $upload = $productVariant->getProduct()->getUpload();
            }
            if ($upload){
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
