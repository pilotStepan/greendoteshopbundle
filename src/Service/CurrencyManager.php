<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;

/**
 * Source of truth for currently selected currency.
 * Keeps selection in session and allows resetting it based on locale changes.
 * Sets currency automatically on each request via CurrencyLocaleListener, unless manually overridden.
 */
class CurrencyManager
{
    private const SESSION_KEY_CURRENCY_ID = 'selectedCurrencyId';
    private const SESSION_KEY_LOCALE = 'selectedCurrencyLocale';

    public function __construct(
        private RequestStack       $requestStack,
        private CurrencyRepository $currencyRepository,
        #[Autowire(param: 'greendot_eshop.shop.secondary_currency_name')]
        private string             $secondaryCurrencyName = 'EUR',
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
     * Currency a given purchase should be priced/displayed in: its own snapshot when set
     * (set at cart creation and kept in sync with its PaymentType's currency), falling back
     * to the current session/default currency for purchases that predate this column.
     *
     * IMPORTANT: only use this to build a Money value. Every plain float/string price must keep
     * using get() (the ambient session/global currency) - never this method - so a bare number
     * that a template or API consumer formats with the ambient currency symbol can never hold a
     * value silently computed in a different currency. See git history around the "Money object
     * pro ceny objednávek" commits for the incident this split fixes.
     */
    public function getForPurchase(Purchase $purchase): Currency
    {
        return $purchase->getCurrency() ?? $this->get();
    }

    /**
     * The currency a document/email should show alongside a given primary currency:
     * the shop's secondary currency (e.g. EUR) when the primary is the default currency,
     * otherwise the default currency itself - so a document never shows the same currency twice.
     */
    public function getSecondaryFor(Currency $primary): Currency
    {
        $secondary = $primary->isIsDefault()
            ? $this->currencyRepository->findOneByIso($this->secondaryCurrencyName)
            : $this->currencyRepository->findOneBy(['isDefault' => true]);

        if (!$secondary) {
            throw new RuntimeException(sprintf('Could not resolve a secondary currency for "%s".', $primary->getIso()));
        }

        return $secondary;
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

    public function getLocale(): string
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (SessionNotFoundException $e) {
            return '';
        }

        return $session->get(self::SESSION_KEY_LOCALE) ?? '';
    }

    private function storeInSession(Currency $currency, ?string $locale): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY_CURRENCY_ID, $currency->getId());
        $session->set(self::SESSION_KEY_LOCALE, $locale);
    }
}