<?php
declare(strict_types=1);

namespace Greendot\EshopBundle\Invoice\Factory;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Service\QRcodeGenerator;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Invoice\Data\VatCategoryData;
use Greendot\EshopBundle\Repository\Project\CountryRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
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
        private CurrencyManager             $currencyManager,
        private CountryRepository           $countryRepository,
        private ParameterRepository         $parameterRepository,
        private QRcodeGenerator             $qrGenerator,
        #[Autowire(param: 'greendot_eshop.shop.secondary_currency_name')]
        private string $secondaryCurrencyName,
    ) {}

    public function create(Purchase $purchase): InvoiceData
    {
        [$czk, $eur] = $this->loadCurrencies();
        $this->purchasePrice = $this->purchasePriceFactory->create($purchase, $czk, VatCalculationType::WithVAT, DiscountCalculationType::WithoutDiscount, VoucherCalculationType::WithoutVoucher);

        $invoiceNumber = $purchase->getInvoiceNumber();
        $isInvoice = $invoiceNumber !== null;


        $dateInvoiced = new \DateTime(
            ($isInvoice ? $purchase->getDateInvoiced() : $purchase->getDateIssue())->format('Y-m-d H:i:s')
        );
        $dateDue = (new \DateTime($dateInvoiced->format('Y-m-d H:i:s')))->modify('+10 days');

        $moneyPrimary = $this->currencyManager->getForPurchase($purchase);
        $moneySecondary = $this->currencyManager->getSecondaryFor($moneyPrimary);

        // $contractor =   $this->buildContractor();
        $customer = $this->buildCustomer($purchase);
        $payment = $this->buildPayment($purchase, $czk, $eur, $moneyPrimary, $moneySecondary);
        $qr = $this->buildQrCode($purchase);
        $transportation = $this->buildTransportation($purchase, $czk, $eur, $moneyPrimary, $moneySecondary);
        $items = $this->buildItems($purchase, $czk, $eur, $moneyPrimary, $moneySecondary);
        $vatCategories = $this->buildVatCategories($purchase, $czk, $eur, $moneyPrimary, $moneySecondary);
        [$discountPercentage, $discountValueCzk, $discountValueEur, $discountValueMoney, $discountValueMoneySecondary] =
            array_values($this->buildDiscount($czk, $eur, $moneyPrimary, $moneySecondary));
        [
            $totalPriceNoVatCzk, $totalPriceNoVatEur,
            $totalPriceVatCzk, $totalPriceVatEur,
            $totalPriceNoVatNoDiscountCzk, $totalPriceNoVatNoDiscountEur,
            $totalPriceVatNoDiscountCzk, $totalPriceVatNoDiscountEur,
            $toPayVatCzk, $toPayVatEur,
            $toPayNoVatCzk, $toPayNoVatEur,
            $totalPriceNoVatMoney, $totalPriceNoVatMoneySecondary,
            $totalPriceVatMoney, $totalPriceVatMoneySecondary,
            $totalPriceNoVatNoDiscountMoney, $totalPriceNoVatNoDiscountMoneySecondary,
            $totalPriceVatNoDiscountMoney, $totalPriceVatNoDiscountMoneySecondary,
        ] = array_values($this->buildPrices($czk, $eur, $moneyPrimary, $moneySecondary));

        [$vouchersUsed, $voucherValueCzk, $voucherValueEur, $voucherValueMoney, $voucherValueMoneySecondary] =
            array_values($this->buildVoucher($czk, $eur, $moneyPrimary, $moneySecondary));

        $purchaseDiscount = $purchase->getClientDiscount();

        [$toPayVatMoney, $toPayNoVatMoney, $toPayVatMoneySecondary, $toPayNoVatMoneySecondary] = $this->buildToPayMoney($purchase);

        return new InvoiceData(
            invoiceId:                              $purchase->getInvoiceNumber(),
            purchaseId:                             $purchase->getId(),
            isInvoice:                              $isInvoice,
            isVatExempted:                          $purchase->isVatExempted(),
            invoiceNumber:                          $invoiceNumber,
            internalNumber:                         $purchase->getInternalNumber(),
            dateInvoiced:                           $dateInvoiced,
            dateDue:                                $dateDue,
            // contractor:                 $contractor,
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
            purchaseDiscount:                       $purchaseDiscount,
            discountPercentage:                     $discountPercentage,
            discountValue:                          $discountValueCzk,
            discountValueSecondary:                 $discountValueEur,
            vouchersUsed:                           $vouchersUsed,
            voucherValue:                           $voucherValueCzk,
            voucherValueSecondary:                  $voucherValueEur,
            toPayVatCzk:                            $toPayVatCzk,
            toPayVatEur:                            $toPayVatEur,
            toPayNoVatCzk:                          $toPayNoVatCzk,
            toPayNoVatEur:                          $toPayNoVatEur,
            toPayVatMoney:                          $toPayVatMoney,
            toPayNoVatMoney:                        $toPayNoVatMoney,
            toPayVatMoneySecondary:                 $toPayVatMoneySecondary,
            toPayNoVatMoneySecondary:               $toPayNoVatMoneySecondary,
            totalPriceNoVatMoney:                   $totalPriceNoVatMoney,
            totalPriceNoVatMoneySecondary:          $totalPriceNoVatMoneySecondary,
            totalPriceVatMoney:                     $totalPriceVatMoney,
            totalPriceVatMoneySecondary:             $totalPriceVatMoneySecondary,
            totalPriceNoVatNoDiscountMoney:          $totalPriceNoVatNoDiscountMoney,
            totalPriceNoVatNoDiscountMoneySecondary: $totalPriceNoVatNoDiscountMoneySecondary,
            totalPriceVatNoDiscountMoney:            $totalPriceVatNoDiscountMoney,
            totalPriceVatNoDiscountMoneySecondary:   $totalPriceVatNoDiscountMoneySecondary,
            discountValueMoney:                      $discountValueMoney,
            discountValueMoneySecondary:              $discountValueMoneySecondary,
            voucherValueMoney:                        $voucherValueMoney,
            voucherValueMoneySecondary:               $voucherValueMoneySecondary,
        );
    }


    /** @return array{Currency, Currency} */
    private function loadCurrencies(): array
    {
        $czk = $this->currencyRepository->findOneBy(['isDefault' => true]);
        $eur = $this->currencyRepository->findOneBy(['name' => $this->secondaryCurrencyName]);

        if (!$czk || !$eur) {
            throw new RuntimeException('Missing CZK or EUR currency in DB.');
        }

        return [$czk, $eur];
    }

    private function buildToPayMoney(Purchase $purchase): array
    {
        $currency = $this->currencyManager->getForPurchase($purchase);
        $calc = $this->purchasePriceFactory->create(
            $purchase,
            $currency,
            VatCalculationType::WithVAT,
            DiscountCalculationType::WithDiscount,
            VoucherCalculationType::WithVoucher,
        );

        $toPayVatMoney = $calc->getMoney(true);
        $toPayNoVatMoney = $calc->setVatCalculationType(VatCalculationType::WithoutVAT)->getMoney(true);

        $secondaryCurrency = $this->currencyManager->getSecondaryFor($currency);
        $calcSecondary = $this->purchasePriceFactory->create(
            $purchase,
            $secondaryCurrency,
            VatCalculationType::WithVAT,
            DiscountCalculationType::WithDiscount,
            VoucherCalculationType::WithVoucher,
        );

        $toPayVatMoneySecondary = $calcSecondary->getMoney(true);
        $toPayNoVatMoneySecondary = $calcSecondary->setVatCalculationType(VatCalculationType::WithoutVAT)->getMoney(true);

        return [$toPayVatMoney, $toPayNoVatMoney, $toPayVatMoneySecondary, $toPayNoVatMoneySecondary];
    }

    private function buildQrCode(Purchase $purchase): ?string
    {
        try {
            return $this->qrGenerator->getFullUrl($purchase);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return InvoiceItemData[] */
    private function buildItems(Purchase $purchase, Currency $currencyPrimary, Currency $currencySecondary, Currency $moneyPrimary, Currency $moneySecondary): array
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

            $priceCalc->setCurrency($moneyPrimary);
            $priceCalc->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
            $priceCalc->setVatCalculationType(VatCalculationType::WithoutVAT);
            $priceNoVatMoney = $priceCalc->getMoney();
            $priceCalc->setVatCalculationType(VatCalculationType::WithVAT);
            $priceVatMoney = $priceCalc->getMoney();
            $priceCalc->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);
            $priceCalc->setVatCalculationType(VatCalculationType::WithoutVAT);
            $priceNoVatNoDiscountMoney = $priceCalc->getMoney();
            $priceCalc->setVatCalculationType(VatCalculationType::WithVAT);
            $priceVatNoDiscountMoney = $priceCalc->getMoney();

            $priceCalc->setCurrency($moneySecondary);
            $priceCalc->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
            $priceCalc->setVatCalculationType(VatCalculationType::WithoutVAT);
            $priceNoVatMoneySecondary = $priceCalc->getMoney();
            $priceCalc->setVatCalculationType(VatCalculationType::WithVAT);
            $priceVatMoneySecondary = $priceCalc->getMoney();
            $priceCalc->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);
            $priceCalc->setVatCalculationType(VatCalculationType::WithoutVAT);
            $priceNoVatNoDiscountMoneySecondary = $priceCalc->getMoney();
            $priceCalc->setVatCalculationType(VatCalculationType::WithVAT);
            $priceVatNoDiscountMoneySecondary = $priceCalc->getMoney();

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
                priceVatNoDiscountSecondary:    $priceVatNoDiscountSecondary,
                parametersLabel:                $this->parameterRepository->getVariantParametersLabel($variant),
                priceNoVatMoney:                          $priceNoVatMoney,
                priceNoVatMoneySecondary:                 $priceNoVatMoneySecondary,
                priceVatMoney:                            $priceVatMoney,
                priceVatMoneySecondary:                   $priceVatMoneySecondary,
                priceNoVatNoDiscountMoney:                $priceNoVatNoDiscountMoney,
                priceNoVatNoDiscountMoneySecondary:       $priceNoVatNoDiscountMoneySecondary,
                priceVatNoDiscountMoney:                  $priceVatNoDiscountMoney,
                priceVatNoDiscountMoneySecondary:         $priceVatNoDiscountMoneySecondary,
            );
        }
        return $items;
    }

    private function buildTransportation(Purchase $purchase, Currency $currencyPrimary, Currency $currencySecondary, Currency $moneyPrimary, Currency $moneySecondary) : InvoiceTransportationData
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

        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithVAT);
        $priceMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getTransportationMoney();
        $priceMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getTransportationMoney();

        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT);
        $priceNoVatMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getTransportationMoney();
        $priceNoVatMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getTransportationMoney();
        $this->purchasePrice->setCurrency($currencyPrimary);

        return new InvoiceTransportationData(
            name:                   $transportation->getName(),
            price:                  $priceVatPrimary,
            priceSecondary:         $priceVatSecondary,
            priceNoVat:             $priceNoVatPrimary,
            priceNoVatSecondary:    $priceNoVatSecondary,
            branchName:             $purchase->getBranch()?->getName(),
            priceMoney:             $priceMoney,
            priceMoneySecondary:    $priceMoneySecondary,
            priceNoVatMoney:        $priceNoVatMoney,
            priceNoVatMoneySecondary: $priceNoVatMoneySecondary,
        );
    }

    private function buildPayment(Purchase $purchase, Currency $currencyPrimary, Currency $currencySecondary, Currency $moneyPrimary, Currency $moneySecondary): InvoicePaymentData
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

        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithVAT);
        $priceMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getPaymentMoney();
        $priceMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getPaymentMoney();

        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT);
        $priceNoVatMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getPaymentMoney();
        $priceNoVatMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getPaymentMoney();
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
            priceMoney:             $priceMoney,
            priceMoneySecondary:    $priceMoneySecondary,
            priceNoVatMoney:        $priceNoVatMoney,
            priceNoVatMoneySecondary: $priceNoVatMoneySecondary,
        );
    }

    /** @return float[] */
    private function buildPrices(Currency $currencyPrimary, Currency $currencySecondary, Currency $moneyPrimary, Currency $moneySecondary): array
    {

        $this->purchasePrice->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount)
            ->setVoucherCalculationType(VoucherCalculationType::WithoutVoucher)
            ->setVatCalculationType(VatCalculationType::WithoutVAT);
        $totalPriceNoVatNoDiscountPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $totalPriceNoVatNoDiscountSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;
        $totalPriceNoVatNoDiscountMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getMoney(true);
        $totalPriceNoVatNoDiscountMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getMoney(true);

        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithVAT);
        $totalPriceVatNoDiscountPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $totalPriceVatNoDiscountSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;
        $totalPriceVatNoDiscountMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getMoney(true);
        $totalPriceVatNoDiscountMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getMoney(true);

        $this->purchasePrice->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
        $totalPriceVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $totalPriceVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;
        $totalPriceVatMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getMoney(true);
        $totalPriceVatMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getMoney(true);

        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT);
        $totalPriceNoVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $totalPriceNoVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;
        $totalPriceNoVatMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getMoney(true);
        $totalPriceNoVatMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getMoney(true);

        $this->purchasePrice->setVoucherCalculationType(VoucherCalculationType::WithVoucher);
        $toPayNoVatCzk = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $toPayNoVatEur = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;
        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithVAT);
        $toPayVatCzk = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $toPayVatEur = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;



        return [
            $totalPriceNoVatPrimary,
            $totalPriceNoVatSecondary,
            $totalPriceVatPrimary,
            $totalPriceVatSecondary,
            $totalPriceNoVatNoDiscountPrimary,
            $totalPriceNoVatNoDiscountSecondary,
            $totalPriceVatNoDiscountPrimary,
            $totalPriceVatNoDiscountSecondary,
            $toPayVatCzk,
            $toPayVatEur,
            $toPayNoVatCzk,
            $toPayNoVatEur,
            $totalPriceNoVatMoney,
            $totalPriceNoVatMoneySecondary,
            $totalPriceVatMoney,
            $totalPriceVatMoneySecondary,
            $totalPriceNoVatNoDiscountMoney,
            $totalPriceNoVatNoDiscountMoneySecondary,
            $totalPriceVatNoDiscountMoney,
            $totalPriceVatNoDiscountMoneySecondary,
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
        if ($purchaseAddress->getShipCountry()) {
            $shipCountry = $this->countryRepository->findByCode($purchaseAddress->getShipCountry())?->getDescription() ?? $purchaseAddress->getShipCountry();
        } else {
            $shipCountry = null;
        }

        return new InvoicePersonData(
            $purchaseAddress->getCompany(),
            "$clientName $clientSurname",
            $purchaseAddress->getStreet(),
            $purchaseAddress->getZip(),
            $purchaseAddress->getCity(),
            $country,
            $purchaseAddress->getIc(),
            $purchaseAddress->getDic(),
            $purchaseAddress->getShipName(),
            $purchaseAddress->getShipSurname(),
            $purchaseAddress->getShipCompany(),
            $purchaseAddress->getShipStreet(),
            $purchaseAddress->getShipZip(),
            $purchaseAddress->getShipCity(),
            $shipCountry,
            $purchaseAddress->getShipIc(),
            $purchaseAddress->getShipDic(),
            $client->getPhone(),
            $client->getMail(),
        );
    }

    private function buildDiscount(Currency $currencyPrimary, Currency $currencySecondary, Currency $moneyPrimary, Currency $moneySecondary) : array
    {
        $this->purchasePrice->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
        $discountPercentage = $this->purchasePrice->getDiscountPercentage();
        $discountValuePrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getDiscountValue();
        $discountValueSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getDiscountValue();
        $discountValueMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getDiscountMoney();
        $discountValueMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getDiscountMoney();

        return [
            $discountPercentage,
            $discountValuePrimary,
            $discountValueSecondary,
            $discountValueMoney,
            $discountValueMoneySecondary,
        ];
    }

    private function buildVoucher(Currency $currencyPrimary, Currency $currencySecondary, Currency $moneyPrimary, Currency $moneySecondary) : array
    {
        $voucherValuePrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getVouchersUsedValue();
        $voucherValueSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getVouchersUsedValue();
        $voucherValueMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getVouchersUsedMoney();
        $voucherValueMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getVouchersUsedMoney();
        $vouchersUsed = $this->purchasePrice->getVouchersUsed();

        return [
            $vouchersUsed,
            $voucherValuePrimary,
            $voucherValueSecondary,
            $voucherValueMoney,
            $voucherValueMoneySecondary,
        ];
    }

    /** @return VatCategoryData[] */
    private function buildVatCategories(Purchase $purchase, Currency $currencyPrimary, Currency $currencySecondary, Currency $moneyPrimary, Currency $moneySecondary) : array
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
            $baseMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getMoney(true, $vatPercentage);
            $baseMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getMoney(true, $vatPercentage);

            $this->purchasePrice->setVatCalculationType(VatCalculationType::OnlyVAT);
            $valuePrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true, $vatPercentage);
            $valueSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true, $vatPercentage);
            $valueMoney = $this->purchasePrice->setCurrency($moneyPrimary)->getMoney(true, $vatPercentage);
            $valueMoneySecondary = $this->purchasePrice->setCurrency($moneySecondary)->getMoney(true, $vatPercentage);

            $vatCategories[] = new VatCategoryData(
                percentage:         $vatPercentage,
                base:               $basePrimary,
                baseSecondary:      $baseSecondary,
                value:              $valuePrimary,
                valueSecondary:     $valueSecondary,
                baseMoney:          $baseMoney,
                baseMoneySecondary: $baseMoneySecondary,
                valueMoney:         $valueMoney,
                valueMoneySecondary: $valueMoneySecondary,
            );
        }

        return $vatCategories;
    }
}