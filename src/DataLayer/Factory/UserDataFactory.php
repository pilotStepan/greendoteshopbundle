<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Google Ads Enhanced Conversions `user_data` for the purchase event, hashed with SHA-256 on the server so plain
 * contact data never reaches the page. Normalization follows Google's rules for pre-hashed data; a value that cannot
 * be normalized reliably (e.g. a phone without a known country prefix) is left out rather than sent wrong.
 */
class UserDataFactory
{
    // calling codes for national phone numbers without a prefix, by billing country
    private const CALLING_CODES = ['CZ' => '420', 'SK' => '421', 'PL' => '48', 'DE' => '49', 'AT' => '43', 'HU' => '36'];
    private const GMAIL_DOMAINS = ['gmail.com', 'googlemail.com'];

    public function __construct(
        #[Autowire(param: 'greendot_eshop.gtm.enhanced_conversions')]
        private readonly bool $enabled = false,
    ) {}

    public function create(Purchase $purchase): ?array
    {
        if (!$this->enabled) return null;

        $client = $purchase->getClient();
        $address = $purchase->getPurchaseAddress();
        $country = $this->normalizeCountry($address?->getCountry());

        $userData = [];
        if ($email = $this->normalizeEmail($client?->getMail())) {
            $userData['sha256_email_address'] = $this->hash($email);
        }
        if ($phone = $this->normalizePhone($client?->getPhone(), $country)) {
            $userData['sha256_phone_number'] = $this->hash($phone);
        }

        // Google matches on the address only with first name, last name, postal code and country together
        $firstName = $this->normalizeName($client?->getName());
        $lastName = $this->normalizeName($client?->getSurname());
        $postalCode = $this->normalizePostalCode($address?->getZip());
        if ($firstName && $lastName && $postalCode && $country) {
            $userData['address'] = [
                'sha256_first_name' => $this->hash($firstName),
                'sha256_last_name'  => $this->hash($lastName),
                'postal_code'       => $postalCode,
                'country'           => $country,
            ];
        }

        return $userData ?: null;
    }

    public function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;

        [$local, $domain] = explode('@', $email, 2);
        if (in_array($domain, self::GMAIL_DOMAINS, true)) {
            $local = str_replace('.', '', $local);
        }

        return $local . '@' . $domain;
    }

    /**
     * Returns the number in E.164 (+420739834430), or null when it has no prefix and the country is unknown.
     */
    public function normalizePhone(?string $phone, ?string $country): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') return null;

        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D/', '', $phone);

        if ($hasPlus) {
            $international = $digits;
        } elseif (str_starts_with($digits, '00')) {
            $international = substr($digits, 2);
        } elseif ($country && isset(self::CALLING_CODES[$country])) {
            $international = self::CALLING_CODES[$country] . ltrim($digits, '0');
        } else {
            return null;
        }

        // E.164: at most 15 digits, and a calling code + subscriber number is never shorter than 8
        if (strlen($international) < 8 || strlen($international) > 15) return null;

        return '+' . $international;
    }

    private function normalizeName(?string $name): ?string
    {
        $name = mb_strtolower(trim(preg_replace('/[\d\p{P}\p{S}]+/u', '', (string) $name)));

        return $name !== '' ? $name : null;
    }

    private function normalizePostalCode(?string $zip): ?string
    {
        $zip = preg_replace('/\s+/', '', (string) $zip);

        return $zip !== '' ? $zip : null;
    }

    private function normalizeCountry(?string $country): ?string
    {
        $country = strtoupper(trim((string) $country));

        return preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
