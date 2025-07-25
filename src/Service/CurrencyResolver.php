<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Currency;
use Symfony\Component\HttpFoundation\RequestStack;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;

readonly class CurrencyResolver
{
    public function __construct(
        private RequestStack       $requestStack,
        private CurrencyRepository $currencyRepository,
    ) {}

    /**
     * Returns the currency selected in session (if set and valid),
     * otherwise falls back to the default (conversion rate = 1).
     */
    public function resolve(): Currency
    {
        $sessionCurrency = $this->requestStack->getSession()->get('currency');

        if ($sessionCurrency instanceof Currency) {
            return $sessionCurrency;
        }

        return $this->currencyRepository->findOneBy(['conversionRate' => 1]);
    }
}