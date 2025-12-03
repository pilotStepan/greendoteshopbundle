<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Currency;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;

/**
 * Source of truth for currently selected currency.
 * Keeps selection in session and allows resetting it based on locale changes.
 * Sets currency automatically on each request via LocaleListener, unless manually overridden.
 */
readonly class CurrencyManager implements LocaleAwareInterface
{
    private const SESSION_KEY_CURRENCY = 'selectedCurrency';
    private const SESSION_KEY_LOCALE = 'selectedCurrencyLocale';

    public function __construct(
        private RequestStack       $requestStack,
        private CurrencyRepository $currencyRepository,
    ) {}

    /**
     * Get currently selected currency from session,
     * or default currency if none is set (no-request operations).
     */
    public function get(): Currency
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (SessionNotFoundException $e) {
            $session = null;
        }

        $sessionCurrency = $session?->get(self::SESSION_KEY_CURRENCY);

        if ($sessionCurrency instanceof Currency) {
            return $sessionCurrency;
        }

        return $this->currencyRepository->findOneBy(['isDefault' => true]);
    }

    /**
     * Called on each request with the current locale.
     * Resets to locale default only when locale has changed or no currency is stored yet.
     */
    public function setByLocale(string $locale): void
    {
        $session = $this->requestStack->getSession();

        $currentCurrency = $session->get(self::SESSION_KEY_CURRENCY);
        $storedLocale = $session->get(self::SESSION_KEY_LOCALE);

        if ($currentCurrency instanceof Currency && $storedLocale === $locale) {
            return;
        }

        $currency = $this->currencyRepository->findCurrencyByLocale($locale);

        $session->set(self::SESSION_KEY_CURRENCY, $currency);
        $session->set(self::SESSION_KEY_LOCALE, $locale);
    }

    /**
     * Manually set currency; keeps it across requests
     * until locale actually changes (handled in setByLocale()).
     */
    public function set(Currency $currency): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY_CURRENCY, $currency);
    }

    public function setLocale(string $locale): void
    {
        $this->setByLocale($locale);
    }

    public function getLocale(): string
    {
        $session = $this->requestStack->getSession();
        return $session->get(self::SESSION_KEY_LOCALE);
    }
}
