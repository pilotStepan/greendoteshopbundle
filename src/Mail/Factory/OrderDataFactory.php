<?php
declare(strict_types=1);

namespace Greendot\EshopBundle\Mail\Factory;

use Throwable;
use LogicException;
use RuntimeException;
use DateTimeImmutable;
use Greendot\EshopBundle\Utils\PriceHelper;
use Greendot\EshopBundle\Mail\Data\OrderData;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Mail\Data\OrderItemData;
use Greendot\EshopBundle\Service\QRcodeGenerator;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Mail\Data\OrderAddressData;
use Greendot\EshopBundle\Mail\Data\OrderPaymentData;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
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
    ) {}

    public function create(Purchase $purchase): OrderData
    {
        [$czk, $eur] = $this->loadCurrencies();
        $this->purchasePrice = $this->purchasePriceFactory->create($purchase, $czk, VatCalculationType::WithVAT);

        $qr = $this->buildQrCode($purchase);
        $payLink = $this->buildPayLink($purchase);
        $items = $this->buildItems($purchase, $czk);
        $transportation = $this->buildTransportation($purchase, $czk, $eur);
        $payment = $this->buildPayment($purchase, $czk, $eur);
        $addresses = $this->buildAddresses($purchase);
        $purchaseNote = $this->extractNote($purchase);
        [$totalPriceVatCzk, $totalPriceVatEur] = array_values($this->buildTotalPrices($czk, $eur));

        return new OrderData(
            purchaseId: $purchase->getId(),
            qrCodeUri: $qr,
            payLink: $payLink,
            trackingUrl: $purchase->getTransportNumber(),
            purchaseNote: $purchaseNote,
            transportation: $transportation,
            payment: $payment,
            addresses: $addresses,
            items: $items,
            primaryCurrency: 'czk', // FIXME
            paid: $this->isPaid($purchase),
            totalPriceVatCzk: $totalPriceVatCzk,
            totalPriceVatEur: $totalPriceVatEur,
        );
    }


    /** @return array{Currency $czk, Currency $eur} */
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
        if ($this->isPaid($purchase)) return null;
        try {
            $dueDate = new DateTimeImmutable('+14 days');
            return $this->qrGenerator->getUri($purchase, $dueDate);
        } catch (Throwable) {
            return null;
        }
    }

    private function buildPayLink(Purchase $purchase): ?string
    {
        if ($this->isPaid($purchase)) return null;
        try {
            $paymentGateway = $this->gatewayProvider->getByPurchase($purchase)
                ?? $this->gatewayProvider->getDefault();
            return $paymentGateway->getPayLink($purchase);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return OrderItemData[] */
    private function buildItems(Purchase $purchase, Currency $currency): array
    {
        $items = [];

        foreach ($purchase->getProductVariants() as $ppv) {
            $priceCalc = $this->productVariantPriceFactory->create($ppv, $currency, vatCalculationType: VatCalculationType::WithVAT);
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

        $priceVatCzk = $this->purchasePrice->setCurrency($czk)->getTransportationPrice() ?? 0.0;
        $priceVatEur = $this->purchasePrice->setCurrency($eur)->getTransportationPrice() ?? 0.0;
        $this->purchasePrice->setCurrency($czk);

        return new OrderTransportationData(
            name: $transportation->getName(),
            description: $transportation->getDescription(),
            priceVatCzk: PriceHelper::formatPrice($priceVatCzk, $czk),
            priceVatEur: PriceHelper::formatPrice($priceVatEur, $eur),
        );
    }

    private function buildPayment(Purchase $purchase, Currency $czk, Currency $eur): OrderPaymentData
    {
        $paymentType = $purchase->getPaymentType();

        $priceVatCzk = $this->purchasePrice->setCurrency($czk)->getPaymentPrice() ?? 0.0;
        $priceVatEur = $this->purchasePrice->setCurrency($eur)->getPaymentPrice() ?? 0.0;
        $this->purchasePrice->setCurrency($czk);

        return new OrderPaymentData(
            type: $this->getTypeFromPaymentType($paymentType),
            name: $paymentType->getName(),
            description: $paymentType->getDescription(),
            priceVatCzk: PriceHelper::formatPrice($priceVatCzk, $czk),
            priceVatEur: PriceHelper::formatPrice($priceVatEur, $eur),
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

    /* return string[] */
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

    private function getTypeFromPaymentType(PaymentType $paymentType): string
    {
        $types = OrderPaymentData::TYPES;
        $id = $paymentType->getId();

        if (!isset($types[$id])) {
            throw new LogicException('Unsupported payment type ID: ' . $id);
        }

        return $types[$id];
    }

    private function isPaid(Purchase $purchase): bool
    {
        // FIXME: not implemented yet. Should be evaluated outside of this factory
        return false;
    }
}