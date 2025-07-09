<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class SessionService
{
    public function __construct(
        private readonly RequestStack       $requestStack,
        private readonly CurrencyRepository $currencyRepository,
    )
    {
    }

    public function getCurrency($symbolOnly = false): Currency|string
    {
        $currency = $this->requestStack->getCurrentRequest()?->getSession()?->get('selectedCurrency')
            ?? $this->currencyRepository->findOneBy(['isDefault' => true]);
        return $symbolOnly ? $currency->getSymbol() : $currency;
    }

}