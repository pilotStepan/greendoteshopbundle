<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Money;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Money\Exception\CurrencyMismatchException;
use JsonSerializable;

/**
 * Minimal, backward-compatible monetary value object: a float amount tied to its
 * ISO 4217 currency code. Introduced alongside (not replacing) the bundle's existing
 * bare-float price API, so a value+currency pair never gets separated by accident.
 *
 * Consider swapping it with https://github.com/brick/money in future releases.
 */
final readonly class Money implements JsonSerializable, \Stringable
{
    public function __construct(
        public float  $value,
        public string $iso,
    ) {}

    /**
     * Build Money from a raw price value and the Currency it was calculated in.
     * A null value is treated as 0.0 (mirrors PriceUtils::convertCurrency()'s null handling).
     */
    public static function fromCurrency(?float $value, Currency $currency): self
    {
        return new self($value ?? 0.0, $currency->getIso());
    }

    public static function zero(string|Currency $currency): self
    {
        return new self(0.0, $currency instanceof Currency ? $currency->getIso() : $currency);
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getIso(): string
    {
        return $this->iso;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->value + $other->value, $this->iso);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->value - $other->value, $this->iso);
    }

    public function multiply(float $factor): self
    {
        return new self($this->value * $factor, $this->iso);
    }

    public function isZero(): bool
    {
        return $this->value === 0.0;
    }

    public function isSameCurrency(self $other): bool
    {
        return $this->iso === $other->iso;
    }

    public function equals(self $other, float $tolerance = PHP_FLOAT_EPSILON): bool
    {
        $this->assertSameCurrency($other);

        return abs($this->value - $other->value) <= $tolerance;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->value > $other->value;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->value < $other->value;
    }

    private function assertSameCurrency(self $other): void
    {
        if (!$this->isSameCurrency($other)) {
            throw new CurrencyMismatchException($this->iso, $other->iso);
        }
    }

    /**
     * @return array{value: float, iso: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'iso' => $this->iso,
        ];
    }

    public function __toString(): string
    {
        return sprintf('%s %s', $this->value, $this->iso);
    }
}
