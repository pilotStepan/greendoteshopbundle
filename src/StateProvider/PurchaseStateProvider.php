<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\PriceCalculator;
use Symfony\Component\HttpFoundation\RequestStack;

class PurchaseStateProvider implements ProviderInterface
{

    private PurchaseRepository $purchaseRepository;
    private RequestStack $requestStack;
    private PriceCalculator $priceCalculator;
    private CurrencyRepository $currencyRepository;

    public function __construct(PurchaseRepository $purchaseRepository,
                                RequestStack       $requestStack,
                                PriceCalculator $priceCalculator,
                                CurrencyRepository $currencyRepository)
    {
        $this->purchaseRepository = $purchaseRepository;
        $this->requestStack = $requestStack;
        $this->priceCalculator = $priceCalculator;
        $this->currencyRepository = $currencyRepository;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Purchase|null
    {
        $purchase =  $this->purchaseRepository->findOneBySession('purchase');
        if($purchase) {
            /*
             * TO-DO find currency in session
             */
            //$currency = $this->requestStack->getCurrentRequest()->get('currency');
            $currency = $this->currencyRepository->findOneBy([]);
            $total_price = $this->priceCalculator->calculatePurchasePrice($purchase, $currency, VatCalculationType::WithVAT, null, DiscountCalculationType::WithDiscount, false, VoucherCalculationType::WithoutVoucher, true);
            $purchase->setTotalPrice($total_price);
            return $purchase;
        }else{
            return null;
        }
    }
}