<?php
declare(strict_types=1);

namespace Greendot\EshopBundle\Invoice\Factory;

use Throwable;
use LogicException;
use RuntimeException;
use DateTimeImmutable;
use Greendot\EshopBundle\Utils\PriceHelper;
use Greendot\EshopBundle\Invoice\Data\InvoiceData;
use Greendot\EshopBundle\Invoice\Data\InvoiceItemData;
use Greendot\EshopBundle\Invoice\Data\InvoicePaymentData;
use Greendot\EshopBundle\Invoice\Data\InvoiceTransportationData;
use Greendot\EshopBundle\Invoice\Data\InvoicePersonData;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Service\QRcodeGenerator;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentGatewayProvider;

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
        private PurchasePriceFactory       $purchasePriceFactory,
        private ProductVariantPriceFactory $productVariantPriceFactory,
        private CurrencyRepository         $currencyRepository,
        private QRcodeGenerator            $qrGenerator,
        private PaymentGatewayProvider     $gatewayProvider,
        private PurchaseUrlGenerator       $purchaseUrlGenerator,
    ) {}

    public function create(Purchase $purchase): InvoiceData
    {
        [$czk, $eur] = $this->loadCurrencies();
        $this->purchasePrice = $this->purchasePriceFactory->create($purchase, $czk, VatCalculationType::WithVAT, DiscountCalculationType::WithoutDiscount, VoucherCalculationType::WithoutVoucher);
        
        $isInvoice = $purchase->getState() === 'paid';

        $dateInvoiced = (new \DateTime($purchase->getDateIssue()->format('Y-m-d H:i:s')));
        $dateDue = (new \DateTime($dateInvoiced->format('Y-m-d H:i:s')))->modify('+10 days');

        // $contractor =   $this->buildContractor();
        $customer = $this->buildCustomer($purchase);
        $payment = $this->buildPayment($purchase, $czk, $eur);
        $qr = $this->buildQrCode($purchase);
        $transportation = $this->buildTransportation($purchase, $czk, $eur);
        $items = $this->buildItems($purchase, $czk, $eur);

        [$discountPercentage, $discountValueCzk, $discountValueEur] = array_values($this->buildDiscount($czk, $eur));
        [$totalPriceNoVatNoDiscountCzk, $totalPriceNoVatNoDiscountEur, $vatValueCzk, $vatValueEur, $totalPriceNoDiscountCzk, $totalPriceNoDiscountEur, $totalPriceVatCzk, $totalPriceVatEur] = array_values($this->buildPrices($czk, $eur));
        [$voucherValueCzk, $voucherValueEur] = array_values($this->buildVoucher($czk, $eur));

        return new InvoiceData(
            invoiceId:                              $purchase->getInvoiceNumber(),
            purchaseId:                             $purchase->getId(),
            isInvoice:                              $isInvoice,
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
            totalPriceNoVatNoDiscount:              $totalPriceNoVatNoDiscountCzk,
            totalPriceNoVatNoDiscountSecondary:     $totalPriceNoVatNoDiscountEur,
            vat:                                    $vatValueCzk,
            vatSecondary:                           $vatValueEur,
            totalPriceNoDiscount:                   $totalPriceNoDiscountCzk,
            totalPriceNoDiscountSecondary:          $totalPriceNoDiscountEur,
            discountPercentage:                     $discountPercentage,
            discountValue:                          $discountValueCzk,
            discountValueSecondary:                 $discountValueEur,
            voucherValue:                           $voucherValueCzk,
            voucherValueSecondary:                  $voucherValueEur,
            totalPrice:                             $totalPriceVatCzk,
            totalPriceSecondary:                    $totalPriceVatEur,
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
            return $this->qrGenerator->getUri($purchase, $dueDate);
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
            $priceCalc = $this->productVariantPriceFactory->create($ppv, $currencyPrimary, vatCalculationType: VatCalculationType::WithoutVAT, discountCalculationType: DiscountCalculationType::WithoutDiscount); 
            $priceNoVat = $priceCalc->getPiecePrice() ?? 0;
            $priceCalc->setCurrency($currencySecondary);
            $priceNoVatSecondary = $priceCalc->getPrice() ?? 0;
            $priceCalc->setVatCalculationType(VatCalculationType::WithVAT);
            $priceVatSecondary = $priceCalc->getPrice() ?? 0;
            $priceCalc->setCurrency($currencyPrimary);
            $priceVat = $priceCalc->getPrice() ?? 0;

            $items[] = new InvoiceItemData(
                name:                   $variant->getName() ?? $product->getName(),
                amount:                 $ppv->getAmount(),
                externalId:             $variant->getExternalId() ?? $product->getExternalId(),
                priceNoVat:             $priceNoVat,
                priceNoVatSecondary:    $priceNoVatSecondary,
                vatPercentage:          $priceCalc->getVatPercentage(),
                priceVat:               $priceVat,
                priceVatSecondary:      $priceVatSecondary,
            );
        }

        return $items;
    }

    private function buildTransportation(Purchase $purchase, Currency $currencyPrimary, Currency $currencySecondary) : InvoiceTransportationData
    {
        $transportation = $purchase->getTransportation();

        $priceVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getTransportationPrice() ?? 0.0;
        $priceVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getTransportationPrice() ?? 0.0;
        $this->purchasePrice->setCurrency($currencyPrimary);

        return new InvoiceTransportationData(
            name:               $transportation->getName(),
            price:              $priceVatPrimary,
            priceSecondary:     $priceVatSecondary,
        );
    }

    private function buildPayment(Purchase $purchase, Currency $currencyPrimary, Currency $currencySecondary): InvoicePaymentData
    {
        $paymentType = $purchase->getPaymentType();

        $priceVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getTransportationPrice() ?? 0;
        $priceVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getTransportationPrice() ?? 0;
        $this->purchasePrice->setCurrency($currencyPrimary);

        return new InvoicePaymentData(
            name:               $paymentType->getName(),
            price:              $priceVatPrimary,
            priceSecondary:     $priceVatSecondary,
            bankAccount:        $paymentType->getAccount(),
            iban:               $paymentType->getIban(),
        );
    }

    /** @return float[] */
    private function buildPrices(Currency $currencyPrimary, Currency $currencySecondary): array
    {
        
        $this->purchasePrice->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount)
                            ->setVoucherCalculationType(VoucherCalculationType::WithoutVoucher)
                            ->setVatCalculationType(VatCalculationType::WithoutVAT);
        $totalPriceNoVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $totalPriceNoVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;

        $this->purchasePrice->setVatCalculationType(VatCalculationType::OnlyVAT);
        $vatValuePrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $vatValueSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;
               
        $this->purchasePrice->setVatCalculationType(VatCalculationType::WithVAT);
        $totalPriceNoDiscountPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $totalPriceNoDiscountSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;

        $this->purchasePrice->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
                            ->setVoucherCalculationType(VoucherCalculationType::WithVoucher);
        $totalPriceVatPrimary = $this->purchasePrice->setCurrency($currencyPrimary)->getPrice(true) ?? 0;
        $totalPriceVatSecondary = $this->purchasePrice->setCurrency($currencySecondary)->getPrice(true) ?? 0;

        return [
            $totalPriceNoVatPrimary,
            $totalPriceNoVatSecondary,
            $vatValuePrimary,
            $vatValueSecondary,
            $totalPriceNoDiscountPrimary,
            $totalPriceNoDiscountSecondary,
            $totalPriceVatPrimary,
            $totalPriceVatSecondary,
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

        return new InvoicePersonData(     
            $purchaseAddress->getCompany(),    
            "$clientName $clientSurname",  
            $purchaseAddress->getStreet(),
            $purchaseAddress->getZip(),  
            $purchaseAddress->getCity(),
            $purchaseAddress->getCountry(),
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
}