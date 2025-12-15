<?php

namespace Greendot\EshopBundle\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Neogate\SmsConnect\SmsConnect;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Symfony\Contracts\Translation\TranslatorInterface;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;

#[WithMonologChannel('notification.sms')]
readonly class ManageSms
{
    public function __construct(
        private TranslatorInterface  $translator,
        private SmsConnect           $client,
        private PurchasePriceFactory $priceFactory,
        private CurrencyManager      $currencyManager,
        private LoggerInterface      $logger,
    ) {}

    /**
     * @throws Exception
     */
    public function sendOrderTransitionSms(Purchase $purchase, string $transition): void
    {
        $phone = $this->processPhone(
            $purchase->getClient()?->getPhone(),
            $purchase->getClient()?->getPrimaryAddress()?->getCountry(),
        );
        if (!$phone) {
            $this->logger->error('Phone number is invalid or missing', [
                'purchase_id' => $purchase->getId(),
                'phone' => $purchase->getClient()?->getPhone(),
                'transition' => $transition,
            ]);
            return;
        };

        $text = $this->getSmsText($purchase, $transition);

        try {
            $this->client->sendSms($phone, $text, sender: 'Yogashop');
        } catch (Exception $e) {
            $this->logger->critical('SMS sending failed', [
                'phone' => $phone,
                'text' => $text,
                'transition' => $transition,
                'exception' => $e,
            ]);
             // throw $e; // Sms doesn't work on local, so ignore for now
        }
    }

    /**
     * Localised subject line from `translations/sms.<locale>.yaml` defined on project side.
     */
    private function getSmsText(Purchase $purchase): string
    {
        $state = $purchase->getState();
        $tracking = $purchase->getTransportNumber();
        $amount = null;

        $key = match ($state) {
            'sent'                     => $tracking ? 'sms.order.sent_with_tracking' : 'sms.order.sent',
            'paid', 'ready_for_pickup' => 'sms.order.' . $state,
            default                    => 'sms.order.default',
        };

        // TODO: make amounts for different currencies, maybe an array for each?
        if ($state === 'paid') {
            $currency = $this->currencyManager->get();
            $amount = $this->priceFactory
                ->create($purchase, $currency, VatCalculationType::WithVAT)
                ->getPrice(true)
            ;
        }

        $params = array_filter([
            '%id%' => $purchase->getId() ?? '',
            '%tracking%' => $tracking,
            '%amount%' => $amount,
        ], static fn($v) => $v !== null && $v !== '');

        return $this->translator->trans($key, $params, 'sms');
    }

    /**
     * Normalises "human" phone input to pure digits accepted by SmsConnect.
     *
     * • Accept +420 777 123 456, 00421-903-123-456, 777 123 456, 0903 123 456 ...
     * • Produce 420777123456 / 421903123456 / ... (no "+", no separators)
     * • Return null when the result is not a 10- to 15-digit E.164 number.
     *
     * TODO: Consider using odolbeau/phone-number-bundle or similar library
     *
     * @param string|null $rawPhone The user-supplied value
     * @param string|null $country  ISO-2 for local fallback, defaults to 'cz'
     */
    private function processPhone(?string $rawPhone, ?string $country = 'cz'): ?string
    {
        if (!$rawPhone = trim((string)$rawPhone)) {
            return null;
        }

        /** Strip whitespace, dashes, brackets ... but keep “+” for now */
        $phone = preg_replace('/[^\d+]/', '', $rawPhone);

        /** Remove leading "+" OR "00" international prefix */
        if ($phone[0] === '+') {
            $phone = substr($phone, 1);
        } else if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        /** Drop a single national trunk ‘0’ (0777 ... -> 777 ..., 0903 ... -> 903 ...) */
        if (str_starts_with($phone, '0') && !str_starts_with($phone, '00')) {
            $phone = substr($phone, 1);
        }

        /** Auto-prepend CZ / SK country code if it is still a local number */
        $needsPrefix = match ($country) {
            'sk'               => (bool)preg_match('/^[1-9]\d{7,8}$/', $phone), // 8–9 digits
            default /* 'cz' */ => (bool)preg_match('/^[1-9]\d{8}$/', $phone), // 9 digits
        };

        if ($needsPrefix) {
            $phone = match ($country) {
                'sk'    => '421' . $phone,
                'cz'    => '420' . $phone,
                default => $phone, // no assumption for other locales
            };
        }

        /** Final E.164 safety net: 10–15 digits, may not start with 0 */
        return preg_match('/^[1-9]\d{9,14}$/', $phone) ? $phone : null;
    }
}