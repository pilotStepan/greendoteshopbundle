<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Greendot\EshopBundle\Service\PriceCalculator;
use Symfony\Component\HttpFoundation\RequestStack;

class CheapTransportationStateProvider implements ProviderInterface
{

    private TransportationRepository $transportationRepository;
    private RequestStack $requestStack;
    private PriceCalculator $priceCalculator;
    private CurrencyRepository $currencyRepository;

    public function __construct(TransportationRepository $transportationRepository,
                                RequestStack       $requestStack,
                                PriceCalculator $priceCalculator,
                                CurrencyRepository $currencyRepository)
    {
        $this->transportationRepository = $transportationRepository;
        $this->requestStack = $requestStack;
        $this->priceCalculator = $priceCalculator;
        $this->currencyRepository = $currencyRepository;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Transportation|null
    {
        $transportation =  $this->transportationRepository->findOneByLowFree();
        if($transportation) {
            /*
             * TO-DO find currency in session
             * TO-DO select default VAT calculation from env
             */
            //$currency = $this->requestStack->getCurrentRequest()->get('currency');
            $currency = $this->currencyRepository->findOneBy([]);


            $transportation->setFreeFromPrice($this->priceCalculator->transportationFreeFrom($transportation));


            return $transportation;
        }else{
            return null;
        }
    }
}