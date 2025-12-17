<?php

namespace Greendot\EshopBundle\Sms\Factory;

use Greendot\EshopBundle\Entity\Project\Purchase;

trait SmsFactoryTrait
{
    /**
     * Normalises "human" phone input to pure digits accepted by SMS clients.
     *
     * • Accept +420 777 123 456, 00421-903-123-456, 777 123 456, 0903 123 456 ...
     * • Produce 420777123456 / 421903123456 / ... (no "+", no separators)
     * • Return null when the result is not a 10- to 15-digit E.164 number.
     */
    private function processPhone(Purchase $purchase): ?string
    {
        $client = $purchase->getClient();
        $rawPhone = $client?->getPhone();
        $country = $client?->getPrimaryAddress()?->getCountry() ?? 'cz';

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