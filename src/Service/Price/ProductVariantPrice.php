<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ConversionRate;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Price;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
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

    private bool $emptyPrice = false;


    public function __construct(
        ProductVariant|PurchaseProductVariant $productVariant,
        ?int                                  $setAmount,
        private ConversionRate                $conversionRate,
        VatCalculationType                    $vatCalculationType,
        DiscountCalculationType               $discountCalculationType,
        private readonly int $afterRegistrationBonus,
        private readonly Security             $security,
        private readonly PriceRepository      $priceRepository,
        private readonly DiscountService      $discountService,
        private readonly PriceUtils           $priceUtils,
        private readonly ?Price                $priceEntity = null
    )
    {
        if ($productVariant instanceof PurchaseProductVariant and !is_null($setAmount)) {
            throw new \Exception('Cannot set amount for ' . PurchaseProductVariant::class);
        }

        $this->vatCalculationType = $vatCalculationType;
        $this->discountCalculationType = $discountCalculationType;
        $this->amount = $setAmount;
        $this->productVariant = $productVariant;
        $this->loadPrice();
        $this->recalculateNoQuery();
    }


    public function getPrice(bool $noConversion = false): ?float
    {
        if ($noConversion) {
            return $this->calculatedPrice;
        }
        return $this->priceUtils->convertCurrency($this->calculatedPrice, $this->conversionRate);
    }

    public function getPiecePrice(): ?float
    {
        $price = $this->getPrice(true);
        if (!$price) {
            return null;
        }
        $piecePrice = $price / $this->amount;
        return $this->priceUtils->convertCurrency($piecePrice, $this->conversionRate);
    }

    public function getMinPrice(bool $noConversion = false): ?float
    {
        if ($noConversion) {
            return $this->minPrice;
        }
        return $this->priceUtils->convertCurrency($this->minPrice, $this->conversionRate);
    }

    public function getVatPercentage(): ?float
    {
        return $this->vatPercentage;
    }

    public function getVatValue(): ?float
    {
        return $this->priceUtils->convertCurrency($this->vatValue, $this->conversionRate);
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
                return $this->clientDiscount ?? $this->afterRegistrationBonus;
        }
        return $this->discountPercentage;
    }

    public function getDiscountValue(): ?float
    {
        return $this->priceUtils->convertCurrency($this->discountValue, $this->conversionRate);
    }

    public function getDiscountTimeUntil(): ?\DateTime
    {
        return $this->discountValidUntil;
    }

    public function getPriceValidUntil(): ?\DateTime
    {
        return $this->priceValidUntil;
    }


    public function setAmount(int $amount): self
    {
        $this->amount = $amount;
        $this->recalculatePrice();
        $this->recalculateNoQuery();
        return $this;
    }

    public function setVatCalculationType(VatCalculationType $vatCalculationType, bool $force = false): self
    {
        $isVatExempted = false;
        if ($this->productVariant instanceof PurchaseProductVariant and $this->productVariant?->getPurchase()){
            $isVatExempted = $this->productVariant->getPurchase()->isVatExempted();
        }

        if (!$isVatExempted or $force) {
            $this->vatCalculationType = $vatCalculationType;
            $this->recalculateNoQuery();
        }

        return $this;
    }

    public function setDiscountCalculationType(DiscountCalculationType $discountCalculationType): self
    {
        $this->discountCalculationType = $discountCalculationType;
        $this->recalculateNoQuery();
        return $this;
    }

    public function setCurrency(Currency|ConversionRate $currencyOrConversionRate): self
    {
        $conversionRate = $currencyOrConversionRate;
        if ($conversionRate instanceof Currency){
            $conversionRate = $this->priceUtils->getConversionRate($currencyOrConversionRate, $this->productVariant instanceof PurchaseProductVariant ? $this->productVariant->getPurchase() : null);
        }
        $this->conversionRate = $conversionRate;

        $this->recalculateNoQuery();
        return $this;
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
                $totalDiscountedPercentage = $this->clientDiscount ?? $this->afterRegistrationBonus;
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
                $fullDiscountValue = $fullDiscountValue + $this->priceUtils->calculatePercentage($fullDiscountValue, $this->vatPercentage);
                break;
            case VatCalculationType::OnlyVAT:
                $price = $this->priceUtils->calculatePercentage($price, $this->vatPercentage);
                $fullDiscountValue = $this->priceUtils->calculatePercentage($fullDiscountValue, $this->vatPercentage);
                break;
        }

        $this->calculatedPrice = $price;
        $this->vatValue = $this->priceUtils->convertCurrency($this->priceUtils->calculatePercentage($this->calculatedPrice, $this->vatPercentage), $this->conversionRate);
        $this->discountValue = $fullDiscountValue;
    }


    private function loadPrice(): void
    {
        if ($this->priceEntity){
            $this->constructForPriceEntity();
            return;
        }elseif ($this->productVariant instanceof PurchaseProductVariant) {
            $this->constructForPurchaseProductVariant();
        } else {
            $this->constructForProductVariant();
        }
        $this->recalculatePrice();
    }

    private function constructForPriceEntity(): void
    {
        if (!$this->priceEntity->getMinimalAmount() or $this->priceEntity->getMinimalAmount() < 1){
            throw new \Exception('Amount and Minimal Amount missing!');

        }
        if (!$this->amount){
            $this->amount = $this->priceEntity->getMinimalAmount();
        }
        $this->minAmount = $this->priceEntity->getMinimalAmount();

        $this->setCurrentUserDiscount();

        $this->minPrice = $this->priceEntity->getMinPrice();
        $this->vatPercentage = $this->priceEntity->getVat();

        if ($this->priceEntity->getDiscount()){
            $this->discountPercentage = $this->priceEntity->getDiscount();
            $this->discountValidUntil = $this->priceEntity->getValidUntil();
        }

        $this->priceValidUntil = $this->priceEntity->getValidUntil();
        $values = $this->calculateValues($this->amount, $this->priceEntity);
        $this->price = $values['price'];
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
        if (!$this->minAmount and !$this->amount){
            $this->emptyPrice = true;
            return;
        }
        $this->setCurrentUserDiscount();
    }

    private function setCurrentUserDiscount(): void
    {
        $client = $this->security->getUser();
        if (!$client || ($client instanceof InMemoryUser && $client->getRoles() === ['ROLE_API'])) {
            // Skip clientDiscount calculation for Simple-ws requests (ROLE_API), as $client is not a Client instance in this case.
            $this->clientDiscount = null;
            return;
        }

        assert($client instanceof Client);
        $clientDiscountObject = $this->discountService->getValidClientDiscount($client);
        $this->clientDiscount = $clientDiscountObject?->getDiscount() ?? null;
    }

    private function recalculatePrice(): void
    {
        if ($this->emptyPrice){
            return;
        }

        if (!$this->amount and $this->minAmount) {
            $this->amount = $this->minAmount;
        } elseif (!$this->amount) {
            throw new \Exception('No amount set');
        }

        $date = new \DateTime("now");
        $productVariant = $this->productVariant;
        if ($this->productVariant instanceof PurchaseProductVariant) {
            if ($this->productVariant?->getPurchase()?->getDateIssue()) {
                $date = $this->productVariant->getPurchase()?->getDateIssue();
            }
            $productVariant = $this->productVariant->getProductVariant();
        }

        if ($this->productVariant instanceof PurchaseProductVariant and $this->productVariant->getPrice()) {
            $customPrice = $this->productVariant->getPrice();
            $customPrices = [];
            if (is_null($customPrice->getDiscount()) or $customPrice->getDiscount() === 0) {
                $customPrices['price'] = $customPrice;
            } else {
                $discountedCustomPrice = $customPrice;
                $customPrice = clone $discountedCustomPrice;
                $customPrice->setDiscount(null);
                $customPrices['discounted'] = $discountedCustomPrice;
                $customPrices['price'] = $customPrice;
            }
            $prices = [$customPrice->getMinimalAmount() => $customPrices];

        } else {
            $prices = $this->priceRepository->findPricesByDateAndProductVariantNew($productVariant, $date, $this->amount);
        }


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