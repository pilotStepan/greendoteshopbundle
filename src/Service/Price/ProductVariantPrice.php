<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;
use Greendot\EshopBundle\Service\DiscountService;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Bundle\SecurityBundle\Security;

class ProductVariantPrice
{
    //phase 1 - always static after initialization
    private ProductVariant|PurchaseProductVariant $productVariant;
    private ?float $clientDiscount = null;
    private ?int $minAmount = null;

    //phase 2 - can be changed by the user - static for purchase product variant
    private ?int $amount = null;

    //always recalculate
    private ?float $price = null;

    private ?float $calculatedPrice = null;
    private ?float $minPrice = null;


    private ?float $vatValue = null;
    private ?float $vatPercentage = null;

    private ?float $discountValue = null;
    private ?float $discountPercentage = null;

    private ?\DateTime $discountValidUntil = null;
    private ?\DateTime $priceValidUntil = null;

    private VatCalculationType $vatCalculationType;
    private DiscountCalculationType $discountCalculationType;

    private Currency $currency;
    private readonly int $afterRegistrationBonus;


    public function __construct(
        ProductVariant|PurchaseProductVariant $productVariant,
        ?int                                  $setAmount,
        Currency                              $currency,
        VatCalculationType                    $vatCalculationType,
        DiscountCalculationType               $discountCalculationType,
        private readonly SettingsRepository   $settingsRepository,
        private readonly Security             $security,
        private readonly PriceRepository      $priceRepository,
        private readonly DiscountService      $discountService,
        private readonly PriceUtils           $priceUtils
    )
    {
        if ($productVariant instanceof PurchaseProductVariant and !is_null($setAmount)) {
            throw new \Exception('Cannot set amount for ' . PurchaseProductVariant::class);
        }

        $this->afterRegistrationBonus = $this->settingsRepository->findParameterValueWithName('after_registration_discount') ?? 0;

        $this->vatCalculationType = $vatCalculationType;
        $this->discountCalculationType = $discountCalculationType;
        $this->amount = $setAmount;
        $this->productVariant = $productVariant;
        $this->currency = $currency;
        $this->loadPrice();
        $this->recalculateNoQuery();
    }


    public function getPrice(bool $noConversion = false): ?float
    {
        if ($noConversion){
            return $this->calculatedPrice;
        }
        return $this->priceUtils->convertCurrency($this->calculatedPrice, $this->currency);
    }

    public function getPiecePrice(): ?float
    {
        $price = $this->getPrice(true);
        if (!$price) {
            return null;
        }
        $piecePrice =  $price / $this->amount;
        return $this->priceUtils->convertCurrency($piecePrice, $this->currency);
    }

    public function getMinPrice(bool $noConversion = false): ?float
    {
        if ($noConversion){
            return $this->minPrice;
        }
        return $this->priceUtils->convertCurrency($this->minPrice, $this->currency);
    }

    public function getVatPercentage(): ?float
    {
        return $this->vatPercentage;
    }

    public function getVatValue(): ?float
    {
        return $this->priceUtils->convertCurrency($this->vatValue, $this->currency);
    }

    public function getDiscountPercentage(): ?float
    {
        switch ($this->discountCalculationType) {
            case DiscountCalculationType::WithDiscount:
            case DiscountCalculationType::WithDiscountPlusAfterRegistrationDiscount:
                $clientDiscount = $this->clientDiscount ?? $this->afterRegistrationBonus;
                return $this->discountPercentage + $clientDiscount;
            case DiscountCalculationType::WithoutDiscount:
                return null;
            case DiscountCalculationType::OnlyProductDiscount:
                return $this->discountPercentage;
            case DiscountCalculationType::WithoutDiscountPlusAfterRegistrationDiscount:
                return $this->afterRegistrationBonus;
        }
        return $this->discountPercentage;
    }

    public function getDiscountValue(): ?float
    {
        return $this->priceUtils->convertCurrency($this->discountValue, $this->currency);
    }

    public function getDiscountTimeUntil(): ?\DateTime
    {
        return $this->discountValidUntil;
    }

    public function getPriceValidUntil(): ?\DateTime
    {
        return $this->priceValidUntil;
    }


    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
        $this->recalculatePrice();
        $this->recalculateNoQuery();
    }

    public function setVatCalculationType(VatCalculationType $vatCalculationType): void
    {
        $this->vatCalculationType = $vatCalculationType;
        $this->recalculateNoQuery();
    }

    public function setDiscountCalculationType(DiscountCalculationType $discountCalculationType): void
    {
        $this->discountCalculationType = $discountCalculationType;
        $this->recalculateNoQuery();
    }

    public function setCurrency(Currency $currency): void
    {
        $this->currency = $currency;
        $this->recalculateNoQuery();
    }

    private function recalculateNoQuery(): void
    {
        if (!$this->price) return;

        $totalDiscountedPercentage = 0;
        switch ($this->discountCalculationType) {
            case DiscountCalculationType::WithoutDiscount:
                break;
            case DiscountCalculationType::WithDiscount:
                $totalDiscountedPercentage = $this->discountPercentage + $this->clientDiscount;
                break;
            case DiscountCalculationType::OnlyProductDiscount:
                $totalDiscountedPercentage = $this->discountPercentage;
                break;
            case DiscountCalculationType::WithDiscountPlusAfterRegistrationDiscount:
                $totalDiscountedPercentage = $this->discountPercentage;
                if (!$this->clientDiscount) {
                    $totalDiscountedPercentage += $this->afterRegistrationBonus;
                }
                break;
            case DiscountCalculationType::WithoutDiscountPlusAfterRegistrationDiscount:
                $totalDiscountedPercentage = $this->afterRegistrationBonus;
                break;
        }

        $fullDiscountValue = $this->priceUtils->calculatePercentage($this->price, $totalDiscountedPercentage);
        $price = $this->price - $fullDiscountValue;
        if ($price < $this->minPrice) {
            $price = $this->minPrice;
        }

        switch ($this->vatCalculationType) {
            case VatCalculationType::WithoutVAT:
                break;
            case VatCalculationType::WithVAT:
                $price = $price + $this->priceUtils->calculatePercentage($price, $this->vatPercentage);
                break;
            case VatCalculationType::OnlyVAT:
                $price = $this->priceUtils->calculatePercentage($price, $this->vatPercentage);
                break;
        }

        $this->calculatedPrice = $price;
        $this->vatValue = $this->priceUtils->convertCurrency($this->priceUtils->calculatePercentage($this->calculatedPrice, $this->vatPercentage), $this->currency);
        $this->discountValue = $fullDiscountValue;
    }


    private function loadPrice(): void
    {
        if ($this->productVariant instanceof PurchaseProductVariant) {
            $this->constructForPurchaseProductVariant();
        } else {
            $this->constructForProductVariant();
        }
        $this->recalculatePrice();
    }

    private function constructForPurchaseProductVariant(): void
    {
        $this->amount = $this->productVariant->getAmount();

        if ($this->productVariant?->getPurchase()?->getClientDiscount()?->getDiscount()) {
            $this->clientDiscount = $this->productVariant->getPurchase()->getClientDiscount()->getDiscount();
        }
    }


    private function constructForProductVariant(): void
    {
        $this->minAmount = $this->priceRepository->getMinimalAmount($this->productVariant, new \DateTime("now"));
        if (!$this->security->getUser()) {
            $this->clientDiscount = null;
            return;
        }

        $client = $this->security->getUser();
        assert($client instanceof Client);
        $clientDiscountObject = $this->discountService->getValidClientDiscount($client);
        $this->clientDiscount = $clientDiscountObject?->getDiscount() ?? null;
    }

    private function recalculatePrice(): void
    {
        if (!$this->amount and $this->minAmount) {
            $this->amount = $this->minAmount;
        } elseif (!$this->amount) {
            throw new \Exception('No amount set');
        }

        $date = new \DateTime("now");
        $productVariant = $this->productVariant;
        if ($this->productVariant instanceof PurchaseProductVariant) {
            if ($this->productVariant?->getPurchase()?->getDateIssue()){
                $date = $this->productVariant->getPurchase()?->getDateIssue();
            }
            $productVariant = $this->productVariant->getProductVariant();
        }


        $prices = $this->priceRepository->findPricesByDateAndProductVariantNew($productVariant, $date, $this->amount);

        if (empty($prices)) {
            $this->price = null;
            return;
        }
        $remainingAmount = $this->amount;

        $this->vatPercentage = 0;

        $priceAmount = 0;
        $vatAmount = 0;
        $discountedAmount = 0;

        $pass = 0;
        foreach ($prices as $twoPrices) {
            $pass = $pass + 1;
            $price = $twoPrices['price'] ?? null;
            $discountedPrice = $twoPrices['discounted'] ?? null;

            if (!$discountedPrice and !$price) {
                throw new \Exception('No price set, this is error in ProductVariantPrice should return null');
            }

            //to prevent if only discounted price is set
            if ($discountedPrice and !$price) {
                $price = $discountedPrice;
            }

            assert($price instanceof Price);
            if ($this->vatPercentage == 0) {
                $this->vatPercentage = $price->getVat();
            } elseif ($this->vatPercentage != $price->getVat()) {
                throw new \Exception("Vat is different for prices on same Project/ProductVariant");
            }


            $amountNumber = $remainingAmount / $price->getMinimalAmount();

            if (!$price->isIsPackage() or $this->amount % $price->getMinimalAmount() == 0) {
                $values = $this->calculateValues($remainingAmount, $price);
                $this->minPrice = $price->getMinPrice() * $remainingAmount;

                $priceAmount += $values['price'];
                $vatAmount += $values['vatAmount'];

                if ($pass === 1) {
                    $this->priceValidUntil = $price->getValidUntil();
                }

                if ($discountedPrice) {
                    assert($discountedPrice instanceof Price);
                    $values = $this->calculateValues($remainingAmount, $discountedPrice);
                    $discountedAmount += $values['discountedAmount'];

                    if ($pass === 1) {
                        $this->discountValidUntil = $discountedPrice->getValidUntil();
                    }

                }
                break;
            }


            //handles isPackage prices
            $amountTaken = (int)$amountNumber * $price->getMinimalAmount();
            $remainingAmount -= $amountTaken;

            $values = $this->calculateValues($amountTaken, $price);
            $this->minPrice = $price->getMinPrice() * $remainingAmount;
            $priceAmount += $values['price'];
            $vatAmount += $values['vatAmount'];

            if ($discountedPrice) {
                assert($discountedPrice instanceof Price);
                $values = $this->calculateValues($amountTaken, $discountedPrice);
                $discountedAmount += $values['discountedAmount'];
            }

        }

        $this->price = $priceAmount;

        $this->vatValue = $vatAmount;
        $this->vatPercentage = $this->priceUtils->calculatePercentage($priceAmount, null, $vatAmount);

        $this->discountValue = $discountedAmount;
        $this->discountPercentage = $this->priceUtils->calculatePercentage($priceAmount, null, $discountedAmount);
    }

    #[ArrayShape(['price' => "int", 'discountedAmount' => "int", 'vatAmount' => "int"])]
    private function calculateValues(int $amount, Price $price): array
    {
        if (!$price->getPrice()) {
            return ['price' => 0, 'discountedAmount' => 0, 'vatAmount' => 0];
        }
        $priceAmount = $price->getPrice() * $amount;

        $discountedAmount = 0;
        if ($price->getDiscount()) {
            $discountedAmount = $this->priceUtils->calculatePercentage($priceAmount, $price->getDiscount());
        }

        $vatAmount = 0;
        if ($price->getVat()) {
            $vatAmount = $this->priceUtils->calculatePercentage($priceAmount, $price->getVat());
        }

        return ['price' => $priceAmount, 'discountedAmount' => $discountedAmount, 'vatAmount' => $vatAmount];
    }


}