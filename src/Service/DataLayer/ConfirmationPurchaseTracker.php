<?php

namespace Greendot\EshopBundle\Service\DataLayer;

use Greendot\EshopBundle\DataLayer\Factory\PurchaseFactory;
use Greendot\EshopBundle\DataLayer\Factory\UserDataFactory;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * With `greendot_eshop.gtm.purchase_on_confirmation_page` enabled, the `purchase` event is not queued (the queue is
 * flushed by whatever page or /gtm/read/all call comes next); the order id is remembered instead and the confirmation
 * page renders the event once per order (template `@GreendotEshop/data_layer/purchase_push.html.twig`).
 */
class ConfirmationPurchaseTracker
{
    private const PENDING_KEY = '_gtm_pending_purchases';
    private const SENT_KEY = '_gtm_sent_purchases';

    // fallback window for buyers whose session did not survive the payment gateway round trip
    private const FALLBACK_MAX_AGE = '-24 hours';

    public function __construct(
        private readonly RequestStack    $requestStack,
        private readonly PurchaseFactory $purchaseFactory,
        private readonly UserDataFactory $userDataFactory,
        #[Autowire(param: 'greendot_eshop.gtm.purchase_on_confirmation_page')]
        private readonly bool            $enabled = false,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function markPending(Purchase $purchase): void
    {
        $session = $this->getSession();
        if (!$session || $purchase->getId() === null) return;

        $transactionId = (string) $purchase->getId();
        $pending = $session->get(self::PENDING_KEY, []);
        if (!in_array($transactionId, $pending, true)) {
            $pending[] = $transactionId;
        }
        $session->set(self::PENDING_KEY, $pending);
    }

    /**
     * Returns the dataLayer `purchase` event, or null when it must not fire on this page view.
     * $justCreated = the page was reached right after checkout / payment; it allows the event for a fresh order
     * that is not pending in this session (session lost during the payment gateway redirect).
     */
    public function consume(Purchase $purchase, bool $justCreated = false): ?array
    {
        if (!$this->enabled || $purchase->getId() === null || $purchase->getOrderNumber() === null) return null;

        $session = $this->getSession();
        $transactionId = (string) $purchase->getId();
        $pending = $session?->get(self::PENDING_KEY, []) ?? [];
        $sent = $session?->get(self::SENT_KEY, []) ?? [];

        if (in_array($transactionId, $sent, true)) return null;

        $isPending = in_array($transactionId, $pending, true);
        $isFreshFallback = $justCreated && $purchase->getDateIssue() > new \DateTime(self::FALLBACK_MAX_AGE);
        if (!$isPending && !$isFreshFallback) return null;

        $session?->set(self::PENDING_KEY, array_values(array_diff($pending, [$transactionId])));
        $session?->set(self::SENT_KEY, [...$sent, $transactionId]);

        $event = ['event' => 'purchase', 'ecommerce' => $this->purchaseFactory->create($purchase)];
        if ($userData = $this->userDataFactory->create($purchase)) {
            $event['user_data'] = $userData;
        }

        return $event;
    }

    private function getSession(): ?SessionInterface
    {
        try {
            return $this->requestStack->getSession();
        } catch (SessionNotFoundException) {
            return null;
        }
    }
}
