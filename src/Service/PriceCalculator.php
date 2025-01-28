<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Repository\Project\ClientDiscountRepository;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Exception;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\SerializerInterface;

class PriceCalculator
{
    private ProductRepository $productRepository;
    private ProductVariantRepository $productVariantRepository;
    private SerializerInterface $serializer;
    private CurrencyRepository $currencyRepository;
    private ClientDiscountRepository $clientDiscountRepository;
    private Security $security;
    public PriceRepository $priceRepository;
    private HandlingPriceRepository $handlingPriceRepository;

    public function __construct(
        ProductRepository        $productRepository,
        ProductVariantRepository $productVariantRepository,
        SerializerInterface      $serializer,
        CurrencyRepository       $currencyRepository,
        ClientDiscountRepository $clientDiscountRepository,
        Security                 $security,
        PriceRepository          $priceRepository,
        HandlingPriceRepository  $handlingPriceRepository
    )
    {
        $this->productRepository = $productRepository;
        $this->productVariantRepository = $productVariantRepository;
        $this->serializer = $serializer;
        $this->currencyRepository = $currencyRepository;
        $this->clientDiscountRepository = $clientDiscountRepository;
        $this->security = $security;
        $this->priceRepository = $priceRepository;
        $this->handlingPriceRepository = $handlingPriceRepository;
    }


    /**
     * Calculates the final purchase price for a given purchase.
     *
     * @param Purchase $purchase The purchase for which the price is to be calculated.
     * @param Currency $currency The currency in which the price is to be calculated.
     * @param VatCalculationType $vatCalculationType The type of VAT calculation to be used.
     * @param int|null $vat_rate The VAT rate to be applied. If null, no VAT is applied.
     * @param DiscountCalculationType $discountCalculationType The type of discount calculation to be used.
     * @param bool $services If true, the service fee is added to the final price.
     * @param VoucherCalculationType $voucherCalculationType The type of voucher calculation to be used.
     * @param bool $do_rounding If true, all calculations are rounded to 2 decimals, final price is rounded to the nearest half.
     *
     * @return float The final calculated price.
     * @throws Exception
     */
    public function calculatePurchasePrice(
        Purchase                $purchase,
        Currency                $currency,
        VatCalculationType      $vatCalculationType,
        int|null                $vat_rate,
        DiscountCalculationType $discountCalculationType,
        bool                    $services,
        VoucherCalculationType  $voucherCalculationType = null,
        bool                    $do_rounding = false,
    ): float
    {
        $finalPrice = 0;
        foreach ($purchase->getProductVariants() as $purchaseProductVariant) {
            if ($vat_rate) {
                $productVariantPrice = $purchaseProductVariant->getProductVariant();
                $prices = $this->priceRepository->findPricesByDateAndProductVariant($productVariantPrice, $purchase->getDateIssue() ?? new \DateTime("now"), $purchaseProductVariant->getAmount());
                if (!empty($prices)) {
                    $finalPrice += $this->calculateProductVariantPrice($purchaseProductVariant, $currency, $vatCalculationType, $discountCalculationType, false, $do_rounding);
                }
            } else {
                $finalPrice += $this->calculateProductVariantPrice($purchaseProductVariant, $currency, $vatCalculationType, $discountCalculationType, false, $do_rounding);
            }
        }

        if ($services) {
            $serviceFee = 0;
            $serviceFee += $this->paymentPrice($purchase, $vatCalculationType, $do_rounding, $currency);
            $serviceFee += $this->transportationPrice($purchase, $vatCalculationType, $currency);
            $finalPrice += $serviceFee;
        }



        if($voucherCalculationType) {
            $finalPrice = $this->applyVouchers($finalPrice, $purchase, $voucherCalculationType, $currency);
        }

        if ($do_rounding) {
            $finalPrice = $this->roundToNearestHalf($finalPrice);
        }

        return $finalPrice;
    }


    /**
     * Calculates the price of a product variant.
     *
     * @param ProductVariant|PurchaseProductVariant $productVariant The product variant for which the price is to be calculated.
     * @param Currency $currency The currency in which the price is to be calculated.
     * @param VatCalculationType $vatCalculationType The type of VAT calculation to be used.
     * @param DiscountCalculationType $discountCalculationType The type of discount calculation to be used.
     * @param bool|null $singleItemPrice If true, the price is calculated for a single item. If false or null, the price is calculated for the entire amount of the product variant.
     * @param bool $do_rounding If true, the final price is rounded to 2 decimal places.
     *
     * @return float The final calculated price.
     * @throws Exception If the VAT rate is different for prices on the same Project/ProductVariant.
     */
    public function calculateProductVariantPrice(
        ProductVariant|PurchaseProductVariant $productVariant,
        Currency                              $currency,
        VatCalculationType                    $vatCalculationType,
        DiscountCalculationType               $discountCalculationType,
        bool|null                             $singleItemPrice = null,
        bool                                  $do_rounding,
    ): float
    {
        $pricesArray = [];
        $priceWithoutVat = 0;
        $vatRate = 0;
        $discountedAmount = 0;
        if (!isset($client) and $this->security->getUser()) {
            $client = $this->security->getUser();
        } else {
            $client = null;
        }

        if ($productVariant instanceof PurchaseProductVariant) {
            $purchaseProductVariant = $productVariant;
            if (!$singleItemPrice) {
                $amount = $purchaseProductVariant->getAmount();
            }
            $productVariant = $purchaseProductVariant->getProductVariant();

            $purchase_client = $purchaseProductVariant->getPurchase()->getClient();
            /*
             * Proc kontrolujeme zda je objednavka v draftu
             */
            if ($purchase_client != null and $purchaseProductVariant->getPurchase()->getState() === "draft") {
                $client = $purchase_client;
            }

        } else {
            $amount = $this->priceRepository->findBy(['productVariant' => $productVariant], ['minimalAmount' => 'ASC']);
            if (isset($amount[0])) {
                $amount = $amount[0]->getMinimalAmount();
            } else {
                $amount = 0;
            }
        }


        if ($client and ($discountCalculationType == DiscountCalculationType::WithDiscount)) {
            $discountedAmount = $this->getClientDiscountValue($client);
        }
        if (isset($purchaseProductVariant) and $purchaseProductVariant->getPurchase()->getDateIssue() != null) {
            $prices = $this->priceRepository->findPricesByDateAndProductVariant($productVariant, $purchaseProductVariant->getPurchase()->getDateIssue(), $amount);
        } else {
            $prices = $this->priceRepository->findPricesByDateAndProductVariant($productVariant, new \DateTime("now"), $amount);
        }

        if (!empty($prices)) {
            $remainingAmount = $amount;
            foreach ($prices as $price) {
                if ($vatRate != 0 and $vatRate != $price->getVat()) {
                    throw new Exception("Vat is different for prices on same Project/ProductVariant");
                } elseif ($vatRate == 0) {
                    $vatRate = $price->getVat();
                }

                $amountNumber = $remainingAmount / $price->getMinimalAmount();

                //only for the WithDiscountPlusAfterRegistrationDiscount, it either applies the client discount or the base registration discount 10%
                if ($client) {
                    $clientDiscount = $this->getClientDiscountValue($client);
                } else {
                    $clientDiscount = 10;
                }

                //if price is not for a package, the price is valid for everything below
                if (!$price->isIsPackage()) {
                    $finalPrice = $this->getFinalPrice($discountCalculationType, $price, $discountedAmount, $clientDiscount);
                    $finalPrice = $this->convertCurrency($finalPrice, $currency);
                    $finalPrice = $finalPrice * $remainingAmount;
                    $priceWithoutVat += $finalPrice;
                    break;
                }
                //if there is no reminder
                if ($amount % $price->getMinimalAmount() != 0) {
                    $amountTaken = (int)$amountNumber * $price->getMinimalAmount();
                    $remainingAmount -= $amountTaken;

                    $finalPrice = $this->getFinalPrice($discountCalculationType, $price, $discountedAmount, $clientDiscount);
                    $finalPrice = $this->convertCurrency($finalPrice, $currency);
                    $finalPrice = $finalPrice * $amountTaken;
                    $priceWithoutVat += $finalPrice;

                    //$priceWithoutVat += $this->getFinalPrice($discountCalculationType, $price, $discountedAmount, $clientDiscount) * $amountTaken;
                } else {
                    //$priceWithoutVat += $this->getFinalPrice($discountCalculationType, $price, $discountedAmount, $clientDiscount) * $remainingAmount;
                    $finalPrice = $this->getFinalPrice($discountCalculationType, $price, $discountedAmount, $clientDiscount);
                    $finalPrice = $this->convertCurrency($finalPrice, $currency);
                    $finalPrice = $finalPrice * $remainingAmount;
                    $priceWithoutVat += $finalPrice;
                    break;
                }
            }

        }
        $returnAmount = $this->applyVat($priceWithoutVat, $vatRate, $vatCalculationType);

        if ($do_rounding) {
            $returnAmount = round($returnAmount, 2);
        }
        return $returnAmount;
    }

    public function transportationFreeFrom(Transportation $transportation){
        $prices = $transportation->getHandlingPrices();
        $free_from = 99000;
        foreach ($prices as $price) {
            if($price->getValidFrom() < new \DateTime("now") && $price->getValidUntil() > new \DateTime("now") && $price->getFreeFromPrice() < $free_from) {
                $free_from = $price->getFreeFromPrice();
            }
        }
        return $free_from;
    }


    public function transportationPrice(Transportation|Purchase $purchaseOrTransportation, VatCalculationType $vatCalculationType, ?Currency $currency = null): float|int
    {
        // check if object is purchase
        if ($purchaseOrTransportation instanceof Purchase) {

            //todo: mua se pri poptavce nepridava zadný transportation
            if ($purchaseOrTransportation->getState() === "inquiry"){
                return 0;
            }
            //zmenit enum na withVat na jinych eshopech ale bdl chce vzdy dopravu zdarma podle ceny bez dph
            $purchasePrice = $this->calculatePurchasePrice($purchaseOrTransportation, $this->currencyRepository->findOneBy(["conversionRate" => 1]), $vatCalculationType, null, DiscountCalculationType::WithDiscount, false);

            // check for null transportation
            if ($purchaseOrTransportation->getTransportation() !== null)
            {
                // get handling price for the date of purchase
                $dateOfPurchase = $purchaseOrTransportation->getDateInvoiced();
                $handlingPrice = $this->handlingPriceRepository->GetByDate($purchaseOrTransportation->getTransportation(), $dateOfPurchase);

                if ($handlingPrice === null) {
                    // Handle the case where $handlingPrice is null
                    $vat = 0;
                    $transportationPrice = 0;
                } else {
                    // $handlingPrice is not null, so now you can safely check the VAT
                    $vat = $handlingPrice->getVat() === null ? 0 : $handlingPrice->getVat();

                    // check purchase is free from transportation price
                    if ($handlingPrice->getFreeFromPrice() < $purchasePrice)
                    {
                        $transportationPrice = 0;
                    }
                    else // transportation is not free from price
                    {
                        $transportationPrice = $handlingPrice->getPrice();
                    }
                }
            }
            else // transportation is null !!! => this shouldn't happen
            {
                // set vars to 0
                $vat = 0;
                $transportationPrice = 0;
            }
        }
        else // object is transportation
        {
            // get handling price to current date
            $handlingPrice = $this->handlingPriceRepository->GetByDate($purchaseOrTransportation->getTransportation());
            // set vars from handling price
            $transportationPrice = $handlingPrice->getPrice();
            $vat = $handlingPrice->getVat();
        }

        // calculate the whole transportation price
        $transportationPrice = $this->applyVat(($transportationPrice ?? 0 ), $vat, $vatCalculationType);
        if ($currency){
            $transportationPrice = $this->convertCurrency($transportationPrice, $currency);
        }
        return ceil($transportationPrice);
    }

    public function paymentPrice(PaymentType|Purchase $purchaseOrPayment, VatCalculationType $vatCalculationType): float|int|null
    {
        // object is Purchase
        if ($purchaseOrPayment instanceof Purchase) {
            //todo: mua se pri poptavce nepridava zadný payment
            if ($purchaseOrPayment->getState() === "inquiry"){
                return 0;
            }
            //zmenit enum na withVat na jinych eshopech ale bdl chce vzdy dopravu zdarma podle ceny bez dph
            $purchasePrice = $this->calculatePurchasePrice($purchaseOrPayment, $this->currencyRepository->findOneBy(["conversionRate" => 1]), VatCalculationType::WithoutVAT, null, DiscountCalculationType::WithDiscount, false);


            // check for null payment
            if ($purchaseOrPayment->getPaymentType() !== null){
                // get handling price for the date of purchase
                $dateOfPurchase = $purchaseOrPayment->getDateInvoiced();
                $handlingPrice = $this->handlingPriceRepository->GetByDate($purchaseOrPayment->getPaymentType(), $dateOfPurchase);

                if ($handlingPrice === null) {
                    $vat = 0;
                    $paymentPrice = 0;
                } else {
                    // check purchase is free from payment price
                    if ($handlingPrice->getFreeFromPrice() < $purchasePrice)
                    {
                        $paymentPrice = 0;
                    }
                    else // payment is not free from price
                    {
                        $paymentPrice = $handlingPrice->getPrice();
                    }
                    $vat = $handlingPrice->getVat() === null ? 0 : $handlingPrice->getVat();
                }
            }
            else // payment is null !!! => this shouldn't happen
            {
                // set vars to 0
                $vat = 0;
                $paymentPrice = 0;
            }
        }
        else // object is paymentType
        {
            // get handling price to current date
            $handlingPrice = $this->handlingPriceRepository->GetByDate($purchaseOrPayment->getTransportation());
            // set vars from handling price
            $paymentPrice = $handlingPrice->getPrice();
            $vat = $handlingPrice->getVat();
        }

        // calculate the whole payment price
        return $this->applyVat($paymentPrice, $vat, $vatCalculationType);
    }

    public function convertCurrency(float $price, Currency $currency): float
    {
        $price = $price * $currency->getConversionRate();
        return round($price, $currency->getRounding());
    }

    private function roundToNearestHalf(float $price): float
    {
        return round($price * 2) / 2;
    }


    private function applyVat(float $price, int $vatRate, VatCalculationType $vatCalculationType): ?float
    {
        switch ($vatCalculationType) {
            case VatCalculationType::WithVAT:
                return round($price * (1 + ($vatRate / 100)), 2);
            case VatCalculationType::WithoutVAT:
                return round($price, 2);
            case VatCalculationType::OnlyVAT:
                return round($price * ($vatRate / 100), 2);
        }
        throw new Exception("Unknown Enum:VatCalculationType case");
    }

    public function applyDiscount($discount, $price): float
    {
        $discountAmount = ($discount / 100) * $price;
        $discountedPrice = $price - $discountAmount;

        return $this->roundToNearestHalf($discountedPrice);
    }

    public function applyGiftVoucher($discount, $price): float
    {
        $discountedPrice = $price - $discount;

        return round($discountedPrice, 2);
    }

    private function getClientDiscountValue($client): float
    {
        $clientDiscount = $this->clientDiscountRepository->findCurrentClientDiscount($client);
        if ($clientDiscount) {
            return $clientDiscount->getDiscount();
        } else {
            return 0;
        }
    }

    /**
     * @throws Exception
     */
    private function getFinalPrice(DiscountCalculationType $discountCalculationType, Price $price, int $additionalDiscount, $clientDiscount): float
    {
        switch ($discountCalculationType) {
            case DiscountCalculationType::WithoutDiscount:
                return $price->getPrice();
            case DiscountCalculationType::WithDiscount:
                $fullDiscount = $price->getDiscount() + $additionalDiscount;
                $discountedPrice = $this->applyDiscount($fullDiscount, $price->getPrice());
                return ($discountedPrice > $price->getMinPrice()) ? $discountedPrice : $price->getMinPrice();
            case DiscountCalculationType::WithDiscountPlusAfterRegistrationDiscount:
                $additionalDiscount += $clientDiscount;
                $fullDiscount = $price->getDiscount() + $additionalDiscount;
                $discountedPrice = $this->applyDiscount($fullDiscount, $price->getPrice());
                return ($discountedPrice > $price->getMinPrice()) ? $discountedPrice : $price->getMinPrice();
            case DiscountCalculationType::WithoutDiscountPlusAfterRegistrationDiscount:
                $discountedPrice = $this->applyDiscount(10, $price->getPrice());
                return ($discountedPrice > $price->getMinPrice()) ? $discountedPrice : $price->getMinPrice();
        }
        throw new Exception("Unknown Enum::discountCalculationType");
    }

    public function applyVouchers(float|int|null         $finalPrice,
                                 Purchase               $purchase,
                                 VoucherCalculationType $voucherCalculationType,
                                 Currency               $currency
    )
    {
        if ($voucherCalculationType == VoucherCalculationType::WithoutVoucher) {
            return $finalPrice;
        }

//        $vouchers = $purchase->getVouchersIssued();
//        $overallDiscountAmount = 0;
//        foreach ($vouchers as $voucher) {
//            $overallDiscountAmount += $voucher->getState() == "paid" ? $voucher->getAmount() : 0;
//        }

        foreach ($purchase->getVouchersUsed() as $voucher) {


            $discountAmount = $voucher->getState() == "paid" ? $voucher->getAmount() : 0;

            if ($currency) {
                $discountAmount = $this->convertCurrency($discountAmount, $currency);
            }
            $finalPrice -= $discountAmount;
            if ($voucherCalculationType != VoucherCalculationType::WithVoucherToMinus) {
                $finalPrice = max($finalPrice, 0);
            }

        }
        return $finalPrice;
    }
}