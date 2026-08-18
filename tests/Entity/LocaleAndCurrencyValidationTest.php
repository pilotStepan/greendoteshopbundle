<?php

namespace Greendot\EshopBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Currency;

/**
 * Covers the M4 fix: Client::$locale, Currency::$defaultLocale and
 * Currency::$name are free-text/API-writable columns fed straight into
 * LocaleSwitcher/the translator/payment gateways. These constraints must
 * actually reject garbage, not just be present as decoration.
 */
class LocaleAndCurrencyValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testClientAcceptsValidLocale(): void
    {
        $client = (new Client())->setLocale('cs');

        $violations = $this->filterFor($client, 'locale');

        $this->assertCount(0, $violations);
    }

    public function testClientRejectsGarbageLocale(): void
    {
        $client = (new Client())->setLocale('not-a-locale;drop-table');

        $violations = $this->filterFor($client, 'locale');

        $this->assertGreaterThan(0, count($violations));
    }

    public function testClientAllowsNullLocale(): void
    {
        $client = new Client(); // locale never set

        $violations = $this->filterFor($client, 'locale');

        $this->assertCount(0, $violations);
    }

    public function testCurrencyAcceptsValidIsoCode(): void
    {
        $currency = (new Currency())->setName('EUR');

        $violations = $this->filterFor($currency, 'name');

        $this->assertCount(0, $violations);
    }

    public function testCurrencyRejectsNonIsoCode(): void
    {
        $currency = (new Currency())->setName('NOTREAL');

        $violations = $this->filterFor($currency, 'name');

        $this->assertGreaterThan(0, count($violations));
    }

    public function testCurrencyRejectsInvalidDefaultLocale(): void
    {
        $currency = (new Currency())->setDefaultLocale('xx_totally_invalid');

        $violations = $this->filterFor($currency, 'defaultLocale');

        $this->assertGreaterThan(0, count($violations));
    }

    private function filterFor(object $object, string $property): \Symfony\Component\Validator\ConstraintViolationListInterface
    {
        return $this->validator->validateProperty($object, $property);
    }
}
