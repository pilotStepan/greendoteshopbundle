<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification\Handler;

use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Service\InvoiceMaker;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Greendot\EshopBundle\Mail\Factory\OrderDataFactory;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;

#[AsTaggedItem(index: 'purchase_notification.customer_email_with_proforma')]
final readonly class CustomerEmailWithProformaHandler implements PurchaseNotificationHandlerInterface
{
    public function __construct(
        private ManageMails         $manageMails,
        private OrderDataFactory    $orderDataFactory,
        private TranslatorInterface $translator,
        private InvoiceMaker        $invoiceMaker,
    ) {}

    public function handle(Purchase $purchase, string $transition): void
    {
        $orderData = $this->orderDataFactory->create($purchase);

        $params = ['%id%' => $purchase->getId() ?? ''];
        $key = 'email.subject.order.' . $transition;
        $subject = $this->translator->trans($key, $params, 'emails');
        if ($subject === $key) {
            $subject = $this->translator->trans('email.subject.order.default', $params, 'emails');
        }

        $email = $this->manageMails->getBaseTemplate()
            ->to($purchase->getClient()->getMail())
            ->subject($subject)
            ->htmlTemplate(sprintf('email/order/%s.html.twig', $transition))
            ->context(['data' => $orderData, 'transition' => $transition])
        ;

        $invoicePath = $this->invoiceMaker->createInvoiceOrProforma($purchase);
        if ($invoicePath) {
            $email->attachFromPath(
                $invoicePath,
                'proforma_' . $purchase->getId() . '.pdf',
                'application/pdf',
            );
        }

        $this->manageMails->sendTemplate($email);
    }
}
