<?php
declare(strict_types=1);

namespace Greendot\EshopBundle\Mail\Factory;

use Throwable;
use RuntimeException;
use DateTimeImmutable;
use Greendot\EshopBundle\Utils\PriceHelper;
use Greendot\EshopBundle\Mail\Data\OrderData;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Mail\Data\OrderItemData;
use Greendot\EshopBundle\Service\QRcodeGenerator;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Greendot\EshopBundle\Mail\Data\OrderAddressData;
use Greendot\EshopBundle\Mail\Data\OrderPaymentData;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Mail\Data\OrderTransportationData;
use Greendot\EshopBundle\Entity\Project\PurchaseDiscussion;
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
final class OrderDataFactory
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

    public function create(Purchase $purchase): OrderData
    {
        [$czk, $eur] = $this->loadCurrencies();
        $vatCalculation = $purchase->isVatExempted() ? VatCalculationType::WithoutVAT : VatCalculationType::WithVAT;
        $this->purchasePrice = $this->purchasePriceFactory->create($purchase, $czk, $vatCalculation);

        $qr = $this->buildQrCode($purchase);
        $payLink = $this->buildPayLink($purchase);
        $items = $this->buildItems($purchase, $czk, $vatCalculation);
        $transportation = $this->buildTransportation($purchase, $czk, $eur);
        $payment = $this->buildPayment($purchase, $czk, $eur);
        $addresses = $this->buildAddresses($purchase);
        $purchaseNote = $this->extractNote($purchase);
        [$totalPriceCzk, $totalPriceEur] = array_values($this->buildTotalPrices($czk, $eur));
        $clientSectionUrl = $this->purchaseUrlGenerator->buildOrderDetailUrl($purchase);

        return new OrderData(
            purchaseId: $purchase->getId(),
            vatExempted: $purchase->isVatExempted(),
            qrCodeUri: $qr,
            payLink: $payLink,
            trackingUrl: $this->purchaseUrlGenerator->buildTrackingUrl($purchase),
            trackingNumber: $purchase->getTransportNumber(),
            purchaseNote: $purchaseNote,
            transportation: $transportation,
            payment: $payment,
            addresses: $addresses,
            items: $items,
            primaryCurrency: 'czk', // FIXME
            paid: $this->isPaid($purchase),
            totalPriceCzk: $totalPriceCzk,
            totalPriceEur: $totalPriceEur,
            clientSectionUrl: $clientSectionUrl,
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
        // Don't build if cant be paid or already paid
        if (!$this->canBePaid($purchase) || $this->isPaid($purchase)) {
            return null;
        }

        try {
            $dueDate = new DateTimeImmutable('+14 days');
            return $this->qrGenerator->getFullUrl($purchase, $dueDate);
        } catch (Throwable) {
            return null;
        }
    }

    private function buildPayLink(Purchase $purchase): ?string
    {
        // Don't build if cant be paid or already paid
        if (!$this->canBePaid($purchase) || $this->isPaid($purchase)) {
            return null;
        }

        try {
            $paymentGateway = $this->gatewayProvider->getByPurchase($purchase)
                ?? $this->gatewayProvider->getDefault();
            return $paymentGateway->getPayLink($purchase);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return OrderItemData[] */
    private function buildItems(Purchase $purchase, Currency $currency, VatCalculationType $vatCalculation): array
    {
        $items = [];

        foreach ($purchase->getProductVariants() as $ppv) {
            $priceCalc = $this->productVariantPriceFactory->create($ppv, $currency, vatCalculationType: $vatCalculation);
            $variant = $ppv->getProductVariant();
            $product = $variant->getProduct();
            $unitPrice = $priceCalc->getPiecePrice() ?? 0.0;
            $totalPrice = $priceCalc->getPrice() ?? 0.0;

            $items[] = new OrderItemData(
                productId: $product->getId(),
                name: $variant->getName() ?? $product->getName(),
                productSlug: $product->getSlug(),
                quantity: $ppv->getAmount(),
                unitPrice: PriceHelper::formatPrice($unitPrice, $currency),
                totalPrice: PriceHelper::formatPrice($totalPrice, $currency),
            );
        }

        return $items;
    }

    private function buildTransportation(Purchase $purchase, Currency $czk, Currency $eur): OrderTransportationData
    {
        $transportation = $purchase->getTransportation();

        $priceCzk = $this->purchasePrice->setCurrency($czk)->getTransportationPrice() ?? 0.0;
        $priceEur = $this->purchasePrice->setCurrency($eur)->getTransportationPrice() ?? 0.0;
        $this->purchasePrice->setCurrency($czk);

        return new OrderTransportationData(
            action: $transportation->getTransportationAction()->value,
            country: $transportation->getCountry(),
            name: $transportation->getName(),
            description: $transportation->getDescription(),
            priceCzk: PriceHelper::formatPrice($priceCzk, $czk),
            priceEur: PriceHelper::formatPrice($priceEur, $eur),
            branchName: $purchase->getBranch()?->getName(),
            mailDescription: $transportation->getDescriptionMail(),
        );
    }

    private function buildPayment(Purchase $purchase, Currency $czk, Currency $eur): OrderPaymentData
    {
        $paymentType = $purchase->getPaymentType();

        $priceCzk = $this->purchasePrice->setCurrency($czk)->getPaymentPrice() ?? 0.0;
        $priceEur = $this->purchasePrice->setCurrency($eur)->getPaymentPrice() ?? 0.0;
        $this->purchasePrice->setCurrency($czk);

        return new OrderPaymentData(
            action: $paymentType->getActionGroup()->value,
            country: $paymentType->getCountry(),
            name: $paymentType->getName(),
            description: $paymentType->getDescription(),
            priceCzk: PriceHelper::formatPrice($priceCzk, $czk),
            priceEur: PriceHelper::formatPrice($priceEur, $eur),
            bankNumber: $paymentType->getBankNumber(),
            bankAccount: $paymentType->getAccount(),
            bankName: $paymentType->getBankName(),
            bankIban: $paymentType->getIban(),
        );
    }

    /** @return array{billing: OrderAddressData, shipping: ?OrderAddressData} */
    private function buildAddresses(Purchase $purchase): array
    {
        $addr = $purchase->getPurchaseAddress();

        $billing = new OrderAddressData(
            fullName: $purchase->getClient()->getFullname(),
            street: $addr->getStreet(),
            city: $addr->getCity(),
            zip: $addr->getZip(),
        );

        $shipping = null;
        if ($addr->getShipStreet() && $addr->getShipCity() && $addr->getShipZip()) {
            $shippingFullName = trim(($addr->getShipName() ?? '') . ' ' . ($addr->getShipSurname() ?? ''))
                ?: $purchase->getClient()->getFullname();

            $shipping = new OrderAddressData(
                fullName: $shippingFullName,
                street: $addr->getShipStreet(),
                city: $addr->getShipCity(),
                zip: $addr->getShipZip(),
            );
        }

        return [
            'billing' => $billing,
            'shipping' => $shipping,
        ];
    }

    private function extractNote(Purchase $purchase): ?string
    {
        /* @var $discussion PurchaseDiscussion|null */
        $discussion = $purchase->getPurchaseDiscussions()->first();
        return ($discussion && !$discussion->getIsAdmin())
            ? $discussion->getContent()
            : null;
    }

    /* @return string[] */
    private function buildTotalPrices(Currency ...$currencies): array
    {
        $totals = [];
        foreach ($currencies as $currency) {
            $this->purchasePrice->setCurrency($currency);
            $price = $this->purchasePrice->getPrice(true) ?? 0.0;
            $totals[] = PriceHelper::formatPrice($price, $currency);
        }
        return $totals;
    }

    private function isPaid(Purchase $purchase): bool
    {
        // Considering purchase is paid if it has invoice number set
        return $purchase->getInvoiceNumber() !== null && $purchase->getInvoiceNumber() !== '';
    }

    private function canBePaid(Purchase $purchase): bool
    {
        if ($this->isPaid($purchase)) {
            return false;
        }

        // Don't allow to pay if cash on delivery
        $actionGroup = $purchase->getPaymentType()->getActionGroup();
        return $actionGroup !== PaymentTypeActionGroup::ON_DELIVERY;
    }
}