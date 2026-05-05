<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Entity\Project\TransportationGroup;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TransportationGroupPrices implements ProviderInterface
{

    public function __construct(
        #[Autowire(service: CollectionProvider::class)]
        private ProviderInterface $collectionProvider,
        #[Autowire(service: ItemProvider::class)]
        private ProviderInterface $itemProvider,
        private readonly CalculatedPricesService $calculatedPricesService,
    ){}

    /**
     * @inheritDoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof Get){
            $item = $this->itemProvider->provide($operation, $uriVariables, $context);
            $this->calculateForItem($item);
            return $item;
        }
        elseif ($operation instanceof GetCollection){
            $collection = $this->collectionProvider->provide($operation, $uriVariables, $context);
            foreach ($collection as $item){
                $this->calculateForItem($item);
            }
            return $collection;
        }
        throw new \Exception('Unsuported operation');
    }

    private function calculateForItem(TransportationGroup $transportationGroup)
    {
        foreach ($transportationGroup->getTransportations() as $transportation){
            $this->calculatedPricesService->makeCalculatedPricesService(
                service: $transportation
            );
        }
    }
}
