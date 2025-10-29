<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Currency;
use Symfony\Component\HttpFoundation\RequestStack;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;

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
        try {
            $session = $this->requestStack->getSession();
        } catch (SessionNotFoundException $e) {
            $session = null;
        }

        $sessionCurrency = $session?->get('currency');

        if ($sessionCurrency instanceof Currency) {
            return $sessionCurrency;
        }

        $defaultCurrency = $this->currencyRepository->findDefaultCurrency();
        if (!$defaultCurrency) throw new \Exception("No default currency set");
        return $defaultCurrency;
    }
}