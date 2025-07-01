<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ManageSms
{
    public function __construct(
        private TranslatorInterface $translator,
    )
    {
    }

    public function sendOrderReceiveSms(Purchase $purchase): void
    {
        // TODO: implement
    }

    public function sendPaymentReceivedSms(Purchase $purchase): void
    {
        // TODO: implement
    }

    public function sendPrepareForPickupSms(Purchase $purchase): void
    {
        // TODO: implement
    }

    /**
     * Localised subject line from `translations/sms.<locale>.yaml` defined on project side.
     */
    private function getSmsText(Purchase $purchase): string
    {
        if (!$purchase->getId()) {
            throw new \LogicException('Objednávka nemá ID – nelze sestavit SMS.');
        }

        $state = $purchase->getState();
        $tracking = $purchase->getTransportNumber();
        $amount = null;

        $key = match ($state) {
            'sent' => $tracking ? 'sms.order.sent_with_tracking' : 'sms.order.sent',
            'paid', 'ready_for_pickup' => 'sms.order.' . $state,
            default => 'sms.order.default',
        };

        if ($state === 'paid') {
            // TODO: calculate $amount
        }

        $params = array_filter([
            '%id%' => $purchase->getId(),
            '%tracking%' => $tracking,
            '%amount%' => $amount,
        ], static fn($v) => $v !== null && $v !== '');

        return $this->translator->trans($key, $params);
    }
}