<?php
declare(strict_types=1);

namespace Greendot\EshopBundle\Invoice\Factory;

use Throwable;
use RuntimeException;
use DateTimeImmutable;
use Greendot\EshopBundle\Invoice\Data\InvoiceData;
use Greendot\EshopBundle\Invoice\Data\InvoiceItemData;
use Greendot\EshopBundle\Invoice\Data\InvoicePaymentData;
use Greendot\EshopBundle\Invoice\Data\InvoiceTransportationData;
use Greendot\EshopBundle\Invoice\Data\InvoicePersonData;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Service\QRcodeGenerator;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Invoice\Data\VatCategoryData;
use Greendot\EshopBundle\Repository\Project\CountryRepository;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;

/**
 * Factory to create OrderData for email notifications.
 *
 * This factory builds the data structure needed for order emails, including
 * items, transportation, payment details, and addresses.
 */
final class InvoiceDataFactory
{
    private PurchasePrice $purchasePrice;

    public function __construct(
        private PurchasePriceFactory        $purchasePriceFactory,
        private ProductVariantPriceFactory  $productVariantPriceFactory,
        private CurrencyRepository          $currencyRepository,
        private CountryRepository           $countryRepository,
        private QRcodeGenerator             $qrGenerator,
    ) {}
public function create(Purchase $purchase): InvoiceData
{
    try {
        [$czk, $eur] = $this->loadCurrencies();
    } catch (\Throwable $e) {
        throw new RuntimeException('Failed to load currencies', previous: $e);
    }

    if (!$czk || !$eur) {
        throw new RuntimeException('Currency loading returned invalid values');
    }

    $this->purchasePrice = $this->purchasePriceFactory->create(
        $purchase,
        $czk,
        VatCalculationType::WithVAT,
        DiscountCalculationType::WithoutDiscount,
        VoucherCalculationType::WithoutVoucher
    );

    if ($this->purchasePrice === null) {
        throw new RuntimeException('Purchase price creation returned null');
    }

    $invoiceNumber = $purchase->getInvoiceNumber();
    $isInvoice = $invoiceNumber !== null;

    $issueDate = $isInvoice
        ? $purchase->getDateInvoiced()
        : $purchase->getDateIssue();

    if (!$issueDate instanceof \DateTimeInterface) {
        throw new RuntimeException('Invalid invoice / issue date on purchase');
    }

    $dateInvoiced = new \DateTime($issueDate->format('Y-m-d H:i:s'));
    $dateDue = (clone $dateInvoiced)->modify('+10 days');

    $customer = $this->buildCustomer($purchase);
    if ($customer === null) {
        throw new RuntimeException('Customer build failed');
    }

    $payment = $this->buildPayment($purchase, $czk, $eur);
    if ($payment === null) {
        throw new RuntimeException('Payment build failed');
    }

    $qr = $this->buildQrCode($purchase);
    if ($qr === null) {
        throw new RuntimeException('QR code build failed');
    }

    $transportation = $this->buildTransportation($purchase, $czk, $eur);
    if ($transportation === null) {
        throw new RuntimeException('Transportation build failed');
    }

    $items = $this->buildItems($purchase, $czk, $eur);
    if (empty($items)) {
        throw new RuntimeException('Invoice items build returned empty result');
    }

    $vatCategories = $this->buildVatCategories($purchase, $czk, $eur);
    if (empty($vatCategories)) {
        throw new RuntimeException('VAT categories build returned empty result');
    }

    $discount = $this->buildDiscount($czk, $eur);
    if (!is_array($discount)) {
        throw new RuntimeException('Discount build failed');
    }

    [
        $discountPercentage,
        $discountValueCzk,
        $discountValueEur
    ] = array_values($discount);

    $prices = $this->buildPrices($czk, $eur);
    if (!is_array($prices)) {
        throw new RuntimeException('Price calculation failed');
    }

    [
        $totalPriceNoVatCzk,
        $totalPriceNoVatEur,
        $totalPriceVatCzk,
        $totalPriceVatEur,
        $totalPriceNoVatNoDiscountCzk,
        $totalPriceNoVatNoDiscountEur,
        $totalPriceVatNoDiscountCzk,
        $totalPriceVatNoDiscountEur
    ] = array_values($prices);

    $voucher = $this->buildVoucher($czk, $eur);
    if (!is_array($voucher)) {
        throw new RuntimeException('Voucher build failed');
    }

    [$voucherValueCzk, $voucherValueEur] = array_values($voucher);

    return new InvoiceData(
        invoiceId:                              $invoiceNumber,
        purchaseId:                             $purchase->getId(),
        isInvoice:                              $isInvoice,
        isVatExempted:                          $purchase->isVatExempted(),
        invoiceNumber:                          $invoiceNumber,
        dateInvoiced:                           $dateInvoiced,
        dateDue:                                $dateDue,
        customer:                               $customer,
        payment:                                $payment,
        qrPath:                                 $qr,
        transportation:                         $transportation,
        currencyPrimary:                        $czk,
        currencySecondary:                      $eur,
        items:                                  $items,
        vatCategories:                          $vatCategories,
        totalPriceNoVat:                        $totalPriceNoVatCzk,
        totalPriceNoVatSecondary:               $totalPriceNoVatEur,
        totalPriceVat:                          $totalPriceVatCzk,
        totalPriceVatSecondary:                 $totalPriceVatEur,
        totalPriceNoVatNoDiscount:              $totalPriceNoVatNoDiscountCzk,
        totalPriceNoVatNoDiscountSecondary:     $totalPriceNoVatNoDiscountEur,
        totalPriceVatNoDiscount:                $totalPriceVatNoDiscountCzk,
        totalPriceVatNoDiscountSecondary:       $totalPriceVatNoDiscountEur,
        discountPercentage:                     $discountPercentage,
        discountValue:                          $discountValueCzk,
        discountValueSecondary:                 $discountValueEur,
        voucherValue:                           $voucherValueCzk,
        voucherValueSecondary:                  $voucherValueEur,
    );
}



    /** @return array{Currency, Currency} */
    private function loadCurrencies(): array
    {
        $czk = $this->currencyRepository->findOneBy(['isDefault' => true]);
        $eur = $this->currencyRepository->findOneBy(['name' => 'Euro']);

        if (!$czk || !$eur) {
            throw new RuntimeException('Missing CZK or EUR currency in DB.');
        }

        return [$czk, $eur];
    }

    private function buildQrCode(Purchase $purchase): ?string
    {
        try {
            $dueDate = new DateTimeImmutable('+14 days');
            return $this->qrGenerator->getFullUrl($purchase, $dueDate);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return InvoiceItemData[] */
    private function buildItems(Purchase $purchase, Currency $currencyPrimary, Currency $currencySecondary): array
    {
        $items = [];

        foreach ($purchase->getProductVariants() as $ppv) {
            $variant = $ppv->getProductVariant();
            $product = $variant->getProduct();

            // calculate prices
            $priceCalc = $this->productVariantPriceFactory->create($ppv, $currencyPrimary);
            $priceCalc->setCurrency($currencyPrimary);

            // no vat yes discount
            $priceCalc->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
            $priceCalc->setVatCalculationType(VatCalculationType::WithoutVAT);
            $priceNoVat = $priceCalc->getPrice() ?? 0;
            // yes vat yes discount
            $priceCalc->setVatCalculationType(VatCalculationType::WithVAT);
            $priceVat = $priceCalc->getPrice() ?? 0;


            // no vat no discount
            $priceCalc->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);
            $priceCalc->setVatCalculationType(VatCalculationType::WithoutVAT);
            $priceNoVatNoDiscount = $priceCalc->getPrice() ?? 0;

            // yes vat no discount
            $priceCalc->setVatCalculationType(VatCalculationType::WithVAT);
            $priceVatNoDiscount = $priceCalc->getPrice() ?? 0;

            // secondary currency and repeat
            $priceCalc->setCurrency($currencySecondary);

            // no vat yes discount
            $priceCalc->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
            $priceCalc->setVatCalculationType(VatCalculationType::WithoutVAT);
            $priceNoVatSecondary = $priceCalc->getPrice() ?? 0;

            // yes vat yes discount
            $priceCalc->setVatCalculationType(VatCalculationType::WithVAT);
            $priceVatSecondary = $priceCalc->getPrice() ?? 0;


            // no vat no discount
            $priceCalc->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);
            $priceCalc->setVatCalculationType(VatCalculationType::WithoutVAT);
            $priceNoVatNoDiscountSecondary = $priceCalc->getPrice() ?? 0;

            // yes vat no discount
            $priceCalc->setVatCalculationType(VatCalculationType::WithVAT);
            $priceVatNoDiscountSecondary = $priceCalc->getPrice() ?? 0;


            $items[] = new InvoiceItemData(
                name:                           $variant->getName() ?? $product->getName(),
                amount:                         $ppv->getAmount(),
                externalId:                     $variant->getExternalId() ?? $product->getExternalId(),
                vatPercentage:                  $priceCalc->getVatPercentage() ?? 0,
                priceNoVat:                     $priceNoVat,
                priceNoVatSecondary:            $priceNoVatSecondary,
                priceVat:                       $priceVat,
                priceVatSecondary:              $priceVatSecondary,
                priceNoVatNoDiscount:           $priceNoVatNoDiscount,
                priceNoVatNoDiscountSecondary:  $priceNoVatNoDiscountSecondary,
                priceVatNoDiscount:             $priceVatNoDiscount,
                priceVatNoDiscountSecondary:    $priceVatNoDiscountSecondary
            );
        }
        return $items;
    }

    private function buildTransportation(Purchase $purchase, Currency $currencyPrimary, Currency $currencySecondary) : InvoiceTransportationData
    {
        $transportation = $purchase->getTransportation();
        
        
        $this->purchasePrice->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
                            ->setVatCalculationType(VatCalculationType::WithVAT);
        $priceVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getTransportationPrice() ?? 0.0;
        $priceVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getTransportationPrice() ?? 0.0;
        $this->purchasePrice->setCurrency($currencyPrimary);

        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT);
        $priceNoVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getTransportationPrice() ?? 0.0;
        $priceNoVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getTransportationPrice() ?? 0.0;
        $this->purchasePrice->setCurrency($currencyPrimary);


        return new InvoiceTransportationData(
            name:                   $transportation->getName(),
            price:                  $priceVatPrimary,
            priceSecondary:         $priceVatSecondary,
            priceNoVat:             $priceNoVatPrimary,
            priceNoVatSecondary:    $priceNoVatSecondary,
        );
    }

    private function buildPayment(Purchase $purchase, Currency $currencyPrimary, Currency $currencySecondary): InvoicePaymentData
    {
        $paymentType = $purchase->getPaymentType();

        $this->purchasePrice->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
                            ->setVatCalculationType(VatCalculationType::WithVAT);
        $priceVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPaymentPrice() ?? 0;
        $priceVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPaymentPrice() ?? 0;
        $this->purchasePrice->setCurrency($currencyPrimary);

        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT);
        $priceNoVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPaymentPrice() ?? 0;
        $priceNoVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPaymentPrice() ?? 0;
        $this->purchasePrice->setCurrency($currencyPrimary);



        return new InvoicePaymentData(
            name:                   $paymentType->getName(),
            price:                  $priceVatPrimary,
            priceSecondary:         $priceVatSecondary,
            priceNoVat:             $priceNoVatPrimary,
            priceNoVatSecondary:    $priceNoVatSecondary,
            bankAccount:            $paymentType->getAccount(),
            bankNumber:             $paymentType->getBankNumber(),
            iban:                   $paymentType->getIban(),
            actionGroup:            $paymentType->getActionGroup(),
        );
    }

    /** @return float[] */
    private function buildPrices(Currency $currencyPrimary, Currency $currencySecondary): array
    {

        $this->purchasePrice->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount)
                            ->setVoucherCalculationType(VoucherCalculationType::WithoutVoucher)
                            ->setVatCalculationType(VatCalculationType::WithoutVAT);
        $totalPriceNoVatNoDiscountPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $totalPriceNoVatNoDiscountSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;

        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithVAT);
        $totalPriceVatNoDiscountPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $totalPriceVatNoDiscountSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;

        $this->purchasePrice->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
                            ->setVoucherCalculationType(VoucherCalculationType::WithVoucher);
        $totalPriceVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $totalPriceVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;

        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT);
        $totalPriceNoVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $totalPriceNoVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;


        return [
            $totalPriceNoVatPrimary,
            $totalPriceNoVatSecondary,
            $totalPriceVatPrimary,
            $totalPriceVatSecondary,
            $totalPriceNoVatNoDiscountPrimary,
            $totalPriceNoVatNoDiscountSecondary,
            $totalPriceVatNoDiscountPrimary,
            $totalPriceVatNoDiscountSecondary,
        ];
    }

    // private function buildContractor() : InvoicePersonData
    // {
    //     // TODO   
    // }

    private function buildCustomer(Purchase $purchase) : InvoicePersonData
    {
        $purchaseAddress = $purchase->getPurchaseAddress();
        $client = $purchase->getClient();
        $clientName = $client->getName();
        $clientSurname = $client->getSurname();
        $country = $this->countryRepository->findByCode($purchaseAddress->getCountry())?->getDescription() ?? $purchaseAddress->getCountry();

        return new InvoicePersonData(
            $purchaseAddress->getCompany(),
            "$clientName $clientSurname",
            $purchaseAddress->getStreet(),
            $purchaseAddress->getZip(),
            $purchaseAddress->getCity(),
            $country,
            $purchaseAddress->getIc(),
            $purchaseAddress->getDic(),
            $client->getPhone(),
            $client->getMail(),
        );
    }

    private function buildDiscount(Currency $currencyPrimary, Currency $currencySecondary) : array
    {
        $this->purchasePrice->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
        $discountPercentage = $this->purchasePrice->getDiscountPercentage();
        $discountValuePrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getDiscountValue();
        $discountValueSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getDiscountValue();

        return [
            $discountPercentage,
            $discountValuePrimary,
            $discountValueSecondary,
        ];
    }

    private function buildVoucher(Currency $currencyPrimary, Currency $currencySecondary) : array
    {
        $voucherValuePrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getVouchersUsedValue();
        $voucherValueSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getVouchersUsedValue();

        return [
            $voucherValuePrimary,
            $voucherValueSecondary,
        ];
    }

    /** @return VatCategoryData[] */
    private function buildVatCategories(Purchase $purchase, Currency $currencyPrimary, Currency $currencySecondary) : array
    {

        // get unique vat percentages product variants, payment and transportation
        $vatPercentages = [];
        foreach ($purchase->getProductVariants() as $ppv) {
            $variantPriceCalc = $this->productVariantPriceFactory->create($ppv, $currencyPrimary);
            $vatPercentages[] = $variantPriceCalc->getVatPercentage();
        }
        $vatPercentages[] = 21; // makeshift for payment and transportation
        $vatPercentages = array_unique($vatPercentages);

        // create vat categories from vat percentages
        $vatCategories = [];
        foreach($vatPercentages as $vatPercentage)
        {
            // check for null values
            if(!is_float($vatPercentage)) 
            {
                continue;
            }
            
            $this->purchasePrice->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
                                ->setVoucherCalculationType(VoucherCalculationType::WithoutVoucher)
                                ->setVatCalculationType(VatCalculationType::WithoutVAT);
            $basePrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true, $vatPercentage);
            $baseSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true, $vatPercentage);

            $this->purchasePrice->setVatCalculationType(VatCalculationType::OnlyVAT);
            $valuePrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true, $vatPercentage);
            $valueSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true, $vatPercentage);

            $vatCategories[] = new VatCategoryData(
                percentage:         $vatPercentage,
                base:               $basePrimary,
                baseSecondary:      $baseSecondary,
                value:              $valuePrimary,
                valueSecondary:     $valueSecondary,
            );
        }

        return $vatCategories;
    }
}