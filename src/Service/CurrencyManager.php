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
    private const SESSION_KEY_CURRENCY_ID = 'selectedCurrencyId';
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
            return $this->currencyRepository->findOneBy(['isDefault' => true]);
        }

        $currencyId = $session->get(self::SESSION_KEY_CURRENCY_ID);

        if ($currencyId) {
            $currency = $this->currencyRepository->find($currencyId);
            if ($currency instanceof Currency) {
                return $currency;
            }
        }

        return $this->currencyRepository->findOneBy(['isDefault' => true]);
    }

    /**
     * Called on each request with the current locale.
     * Resets to locale default only when locale has changed or no currency is stored yet.
     */
    public function setByLocale(string $locale): void
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (SessionNotFoundException $e) {
            // No session, cannot store currency. (Messenger, CLI, ...)
            // Project's default currency will be used on get().
            return;
        }

        $storedLocale = $session->get(self::SESSION_KEY_LOCALE);

        if ($storedLocale === $locale && $session->has(self::SESSION_KEY_CURRENCY_ID)) {
            return;
        }

        $currency = $this->currencyRepository->findCurrencyByLocale($locale);

        if (!$currency) {
            $currency = $this->currencyRepository->findOneBy(['isDefault' => true]);
        }

        $this->storeInSession($currency, $locale);
    }

    /**
     * Manually set currency; keeps it across requests
     * until locale actually changes (handled in setByLocale()).
     */
    public function set(Currency $currency): void
    {
        $currentLocale = $this->requestStack->getCurrentRequest()?->getLocale();

        if (!$currentLocale) {
            $currentLocale = $this->getLocale();
        }

        $this->storeInSession($currency, $currentLocale);
    }

    public function setLocale(string $locale): void
    {
        $this->setByLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY_LOCALE) ?? '';
    }

    private function storeInSession(Currency $currency, ?string $locale): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY_CURRENCY_ID, $currency->getId());
        $session->set(self::SESSION_KEY_LOCALE, $locale);
    }
}