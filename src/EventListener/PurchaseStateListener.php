<?php

namespace Greendot\EshopBundle\EventListener;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\CzechPostParcel;
use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Service\ManageVoucher;
use Greendot\EshopBundle\Service\PacketeryParcel;
use Greendot\EshopBundle\Service\InvoiceMaker;
use Greendot\EshopBundle\Service\PriceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Registry;

class PurchaseStateListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ManageMails            $manageMails,
        private readonly CzechPostParcel        $czechPostParcel,
        private readonly PacketeryParcel        $packeteryParcel,
        private readonly LoggerInterface        $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly Registry               $registry,
        private readonly InvoiceMaker           $invoiceMaker,
        private readonly PriceCalculator        $priceCalculator,
        private readonly PurchaseRepository     $purchaseRepository,
        private readonly ManageVoucher          $manageVoucher,
        private readonly VoucherListener        $voucherListener,
        private readonly ?SessionInterface      $session = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.purchase_flow.transition.create'             => ['onCreate'],
            'workflow.purchase_flow.transition.receive'            => ['onReceive'],
            'workflow.purchase_flow.transition.payment'            => ['onPayment'],
            'workflow.purchase_flow.transition.payment_issue'      => ['onPaymentIssue'],
            'workflow.purchase_flow.transition.cancellation'       => ['onCancellation'],
            'workflow.purchase_flow.transition.prepare_for_pickup' => ['onPrepareForPickup'],
            'workflow.purchase_flow.transition.send'               => ['onSend'],
            'workflow.purchase_flow.transition.pick_up'            => ['onPickUp'],
        ];
    }

    public function onCreate(Event $event): void
    {
        ///////////////////
        ///////////////////
        //// ZATÍM NIC ////
        ///////////////////
        ///////////////////
    }

    public function onReceive(Event $event): void
    {
        $subject = $event->getSubject();

        if (!$subject instanceof Purchase) {
            throw new \LogicException('Expected subject of type Purchase, got ' . get_class($subject));
        }

        $purchase = $subject;
        $purchasePrice = $this->getPurchasePrice($purchase);

        $this->generateTransportData($purchase, $purchasePrice);
        $this->voucherListener->handleVouchers($purchase, 'used');

        foreach ($purchase->getProductVariants() as $productVariant) {
            if ($productVariant->getProductType()->getName() === 'Dárkový certifikát') {
                $this->manageVoucher->initiateVoucher($productVariant, $purchase);
            }
        }

        $this->manageMails->sendOrderReceiveEmail($purchase, $purchasePrice, 'mail/specific/order-receive.html.twig');
    }

    public function onPayment(Event $event): void
    {
        $subject = $event->getSubject();

        if (!$subject instanceof Purchase) {
            throw new \LogicException('Expected subject of type Purchase, got ' . get_class($subject));
        }

        $purchase = $subject;

        $invoiceNumber = $this->purchaseRepository->getNextInvoiceNumber();
        $invoicePath   = $this->invoiceMaker->createInvoiceOrProforma($purchase);

        $purchase->setInvoiceNumber($invoiceNumber);
        $this->manageMails->sendPaymentReceivedEmail($purchase, $invoicePath, 'mail/specific/payment-received.html.twig');
        $this->voucherListener->handleVouchers($purchase, 'paid');
        $this->entityManager->flush();
    }

    public function onPaymentIssue(Event $event): void
    {
        $purchase = $event->getSubject();

        $this->manageMails->sendNotReceivedEmail($purchase, 'mail/specific/payment-not-received.html.twig');
        $this->voucherListener->handleVouchers($purchase, 'not_paid');
    }

    public function onCancellation(Event $event): void
    {
        $purchase = $event->getSubject();
        $this->manageMails->sendEmail($purchase, 'mail/specific/order-canceled.html.twig');
        $this->voucherListener->handleVouchers($purchase, 'not_paid');
    }

    public function onPrepareForPickup(Event $event): void
    {
        $purchase = $event->getSubject();
        $this->manageMails->sendOrderEmail($purchase, 'mail/specific/order-ready-for-pickup.html.twig');
    }

    public function onSend(Event $event): void
    {
        $purchase = $event->getSubject();
        $this->manageMails->sendOrderEmail($purchase, 'mail/specific/order-shipped.html.twig');
    }

    public function onPickUp(Event $event): void
    {
        $purchase = $event->getSubject();
        $this->manageMails->sendOrderEmail($purchase, 'mail/specific/order-picked-up.html.twig');
    }

    /*
     * TODO Vyhodit a dát do ManageOrder, nebo PriceCalculator service
     */
    private function getPurchasePrice(Purchase $purchase): float
    {
        $currency = $this->session->get('selectedCurrency');

        return $this->priceCalculator->calculatePurchasePrice(
            $purchase,
            $currency,
            VatCalculationType::WithVAT,
            1,
            DiscountCalculationType::WithDiscount,
            true,
            VoucherCalculationType::WithoutVoucher,
            true
        );
    }

    /*
     * TODO Vyhodit a dát do ManageOrder service
     */
    private function generateTransportData(Purchase $purchase, $purchasePrice): void
    {
        $transportationId = $purchase->getTransportation()->getId();
        $parcelId         = null;

        switch ($transportationId) {
            case 4:
                $parcelId = $this->czechPostParcel->createParcel($purchase, $purchasePrice);
                break;
            case 3:
                $parcelId = $this->packeteryParcel->createParcel($purchase, $purchasePrice);
                break;
        }

        if ($parcelId) {
            $purchase->setTransportNumber($parcelId);
        } else {
            $purchaseFlow = $this->registry->get($purchase);
            if ($purchaseFlow->can($purchase, 'cancellation')) {
                $purchaseFlow->apply($purchase, 'cancellation');
                $this->logger->error('Failed to create parcel for purchase. Order cancelled.', ['purchaseId' => $purchase->getId()]);
            } else {
                $this->logger->error('Failed to create parcel for purchase and unable to cancel.', ['purchaseId' => $purchase->getId()]);
            }
        }
    }
}