<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\EventSubscriber\ParameterEventListener;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Service\ListenerManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FilteredParametersStateProvider implements ProviderInterface
{
    public function __construct(
        private ParameterRepository     $parameterRepository,
        private PriceRepository         $priceRepository,
        private EntityManagerInterface  $em,
        private ListenerManager         $listenerManager,
        private ParameterBagInterface   $parameterBag,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {

        // disable for performance
        $this->listenerManager->disable(ParameterEventListener::class);

        $qb = $this->parameterRepository->createQueryBuilder('p');
        $qb->select('p');


        
        $filters = $context['filters']; 

        $vatCalculationTypeValue = $filters['vat_type'] ?? $this->parameterBag->get('greendot_eshop.shop.default_vat_type');
        $vatCalculationType = VatCalculationType::from($vatCalculationTypeValue);

        $discountCalculationTypeValue = $filters['discount_type'] ?? $this->parameterBag->get('greendot_eshop.shop.default_discount_type');
        $discountCalculationType = DiscountCalculationType::from($discountCalculationTypeValue);

        if (!empty($filters['category_id'])) {
            $categoryId = $filters['category_id'];
            $this->parameterRepository->getProductParametersByTopCategory($qb, $categoryId);
            $priceMinMax = $this->priceRepository->getPriceMinMaxForCategory($vatCalculationType, $discountCalculationType, $categoryId);
        }
        if (!empty($filters['supplier_id'])) {
            $supplierId = $filters['supplier_id'];
            $this->parameterRepository->getProductParametersByProducer($qb, $supplierId);
            $priceMinMax = $this->priceRepository->getPriceMinMaxForProducer($vatCalculationType, $discountCalculationType, $supplierId);
        }
        if (!empty($filters['discounts'])) {
            $this->parameterRepository->getProductParametersByDiscount($qb);
            $priceMinMax = $this->priceRepository->getPriceMinMaxForDiscount($vatCalculationType, $discountCalculationType);
        }

        // add color names
        $qb ->leftJoin('Greendot\EshopBundle\Entity\Project\Colour', 'c', 'WITH', 'c.hex = p.data') // <- join color      
            ->addSelect('c.name as colorName');
        $results = $qb->getQuery()->getResult();
        foreach ($results as $row) {
            $parameter = $row[0];
            $this->em->detach($parameter);
            $parameter->setColorName($row['colorName']);
        }


        // create fake price
        $priceVirtualParamGroup = [
            "id" => "price",
            "name" => "Cena",
            "unit" => "Kč",
            "type" => [ "id" => 0, "name" => "string"],
            "parameterGroupFilterType" => [ "id" => 1, "name" => "range"],
            "isProductParameter" => true,
            "isFilter" => true,
        ];    
        $parameters = [];
        if (isset($priceMinMax)) {
            $parameters = [
                ['data' => $priceMinMax['priceMin'], 'parameterGroup' => $priceVirtualParamGroup],
                ['data' => $priceMinMax['priceMax'], 'parameterGroup' => $priceVirtualParamGroup]
            ];
        }
        $parameters = array_merge($parameters, array_map(fn($row) => $row[0], $results));

       
        return $parameters;
    }

}