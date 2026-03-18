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

// TODO: move calculatedPrices creation to provider
#[AsEntityListener(event: Events::postLoad, priority: 10, method: 'postLoad', entity: Product::class)]
class ProductEventListener
{

    public function __construct(
        private ProductRepository       $productRepository,
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
            $upload = $this->productRepository->findProductUploadSubstitute($product);
            if ($upload) {
                $upload->setIsDynamicallySet(true);
                $product->setUpload($upload);
            }
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