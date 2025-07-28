<?php

namespace Greendot\EshopBundle\Service;

use Symfony\Component\Mime\Address;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Greendot\EshopBundle\Mail\Factory\OrderDataFactory;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

readonly class ManageMails
{
    private Address $fromAddress;

    public function __construct(
        private MailerInterface      $mailer,
        private LocaleAwareInterface $localeAware,
        private TranslatorInterface  $translator,
        private RequestStack         $requestStack,
        private ManagerRegistry      $managerRegistry,
        private OrderDataFactory     $dataFactory,
        private CertificateMaker     $certificateMaker,
        private InvoiceMaker         $invoiceMaker,
        private string               $fromEmail,
        private string               $fromName,
    )
    {
        $this->fromAddress = new Address($this->fromEmail, $this->fromName);
    }

    public function sendPurchaseTransitionEmail(Purchase $purchase, string $transition): void
    {
        $orderData = $this->dataFactory->create($purchase);

        $email = match ($transition) {
            'receive'            => $this->buildReceiveEmail($purchase, $orderData),
            'payment'            => $this->buildPaymentEmail($purchase, $orderData),
            'payment_issue'      => $this->buildPaymentIssueEmail($purchase, $orderData),
            'cancellation'       => $this->buildCancellationEmail($purchase, $orderData),
            'prepare_for_pickup' => $this->buildPrepareForPickupEmail($purchase, $orderData),
            'send'               => $this->buildSendEmail($purchase, $orderData),
            'pick_up'            => $this->buildPickUpEmail($purchase, $orderData),
            default              => throw new \InvalidArgumentException('Unknown transition: ' . $transition),
        };

        $this->mailer->send($email);
    }

    /**
     * Sends a password reset email to the user.
     */
    public function sendPasswordResetEmail(string $recipientEmail, ResetPasswordToken $resetToken): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($recipientEmail)
            ->subject($this->translator->trans('email.subject.password_reset', [], 'emails'))
            ->htmlTemplate('email/auth/password_reset.html.twig')
            ->context(['resetToken' => $resetToken])
        ;

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
//            $this->logger->error('…', ['exception' => $e]);
        }
    }

    public function sendFreeSampleMailToInfo($formData, Product $product): void
    {
        $email = new TemplatedEmail();
        $email
            ->subject($this->translator->trans('email.free_sample.subject', [], 'emails'))
            ->addFrom($formData['mail'])
            ->addTo($this->fromAddress)
        ;

//        TODO: make this
//        $email->htmlTemplate('email/free-sample.html.twig')
//            ->context([
//                'content'     => $content,
//                'href'        => $link,
//                'button_name' => $buttonName
//            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
//            $this->logger->error('…', ['exception' => $e]);
            dd($e);
        }
    }

    private function buildReceiveEmail(Purchase $purchase, $orderData): TemplatedEmail
    {

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate('email/order/receive.html.twig')
            ->context(['data' => $orderData])
        ;

        // Attach invoice
        $invoicePath = $this->invoiceMaker->createInvoiceOrProforma($purchase);
        if ($invoicePath) {
            $email->attachFromPath(
                $invoicePath,
                'proforma_' . $purchase->getId() . '.pdf',
                'application/pdf',
            );
        }

        return $email;

    }

    private function buildPaymentEmail(Purchase $purchase, $orderData): TemplatedEmail
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate('email/order/payment.html.twig')
            ->context(['data' => $orderData])
        ;

        // TODO: Uncomment when ready
        // Attach vouchers if any
        /*        foreach ($purchase->getVouchersIssued() as $voucher) {
                    $certificatePath = $this->certificateMaker->createCertificate($voucher);
                    $email->attachFromPath(
                        $certificatePath,
                        'voucher_' . $voucher->getId() . '.pdf',
                        'application/pdf'
                    );
                }*/

        // Attach invoice
        $invoicePath = $this->invoiceMaker->createInvoiceOrProforma($purchase);
        if ($invoicePath) {
            $email->attachFromPath(
                $invoicePath,
                'faktura_' . $purchase->getId() . '.pdf',
                'application/pdf',
            );
        }

        return $email;
    }

    private function buildPaymentIssueEmail(Purchase $purchase, $orderData): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate('email/order/payment_issue.html.twig')
            ->context(['data' => $orderData])
        ;
    }

    private function buildCancellationEmail(Purchase $purchase, $orderData): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate('email/order/cancellation.html.twig')
            ->context(['data' => $orderData])
        ;
    }

    private function buildPrepareForPickupEmail(Purchase $purchase, $orderData): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate('email/order/prepare_for_pickup.html.twig')
            ->context(['data' => $orderData])
        ;
    }

    private function buildSendEmail(Purchase $purchase, $orderData): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate('email/order/send.html.twig')
            ->context(['data' => $orderData])
        ;
    }

    private function buildPickUpEmail(Purchase $purchase, $orderData): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate('email/order/pick_up.html.twig')
            ->context(['data' => $orderData])
        ;
    }

    /**
     * Localised subject line from `translations/emails.<locale>.yaml` defined on project side.
     */
    private function getEmailSubject(Purchase $purchase): string
    {
        $state = $purchase->getState();

        // pick a translation key, fall back to default
        $key = match ($state) {
            'created', 'paid', 'not_paid',
            'sent', 'ready_for_pickup', 'picked_up',
            'canceled', 'received',
                    => 'email.subject.order.' . $state,
            default => 'email.subject.order.default',
        };

        return $this->translator->trans($key, [
            '%id%' => $purchase->getId(),
        ], 'emails');
    }

    private function setLocaleAndRefreshEntities(Purchase $purchase): void
    {
        if (!$this->requestStack->getCurrentRequest() || !$this->requestStack->getCurrentRequest()->getLocale()) {
            $this->localeAware->setLocale('cs');

            $entityManager = $this->managerRegistry->getManager();

            $transportation = $this->refreshEntity($purchase->getTransportation(), 'cs');
            $payment = $this->refreshEntity($purchase->getPaymentType(), 'cs');

            $entityManager->refresh($purchase);

            $purchase->setTransportation($transportation);
            $purchase->setPaymentType($payment);
        }
    }

    private function refreshEntity($entity, string $locale)
    {
        $refreshedEntity = $this->managerRegistry->getRepository(get_class($entity))->find($entity->getId());
        $refreshedEntity->setTranslatableLocale($locale);
        return $refreshedEntity;
    }
}