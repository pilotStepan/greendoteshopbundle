<?php

namespace Greendot\EshopBundle\Service;

use Exception;
use Greendot\EshopBundle\Entity\Project\Currency;
use Symfony\Component\HttpFoundation\RequestStack;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;

class SessionService
{
    public function __construct(
        private readonly RequestStack       $requestStack,
        private readonly CurrencyRepository $currencyRepository,
    ) {}

    public function getCurrency($symbolOnly = false): Currency|string
    {
        try {
            $session = $this->requestStack->getCurrentRequest()?->getSession();
        } catch (Exception $e) {
            $session = null;
        }

        $currency = $session?->get('selectedCurrency')
            ?? $this->currencyRepository->findOneBy(['isDefault' => true]);
        return $symbolOnly ? $currency->getSymbol() : $currency;
    }
}