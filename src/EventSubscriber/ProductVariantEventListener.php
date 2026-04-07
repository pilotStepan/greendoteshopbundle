<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Greendot\EshopBundle\Service\ListenerManager;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;

#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: ProductVariant::class)]
class ProductVariantEventListener
{
    public function __construct(
        private CalculatedPricesService $calculatedPricesService,
        private ListenerManager         $listenerManager,
        private readonly ProductVariantRepository $productVariantRepository
    ) {}

    // TODO: move calculatedPrices creation to provider
    public function postLoad(ProductVariant $productVariant, PostLoadEventArgs $event): void
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
