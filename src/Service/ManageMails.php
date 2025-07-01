<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PaymentRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

readonly class ManageMails
{
    private Address $fromAddress;

    public function __construct(
        private MailerInterface      $mailer,
        private LocaleAwareInterface $localeAware,
        private TranslatorInterface  $translator,
        private RequestStack         $requestStack,
        private ManagerRegistry      $managerRegistry,
        private PaymentRepository    $paymentRepository,
        private QRcodeGenerator      $qrCodeGenerator,
        private GPWebpay             $webpay,
        private string               $fromEmail,
        private string               $fromName,
    )
    {
        $this->fromAddress = new Address($this->fromEmail, $this->fromName);
    }

    public function sendOrderReceiveEmail(Purchase $purchase, string $template): void
    {
        $varSymbol = $this->paymentRepository->findByPurchaseId($purchase->getId());
        $dueDate = new \DateTime('+14 days');
        $qrCodeUri = $this->qrCodeGenerator->getUri($purchase, $dueDate);
        $paymentUrl = $this->webpay->getPayLink($purchase, $varSymbol);

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate($template)
            ->context([
                'purchase_price' => $purchase->getTotalPrice(),
                'var_symbol' => $varSymbol,
                'bank_account' => $purchase->getPaymentType()->getAccount(),
                'payment_type' => $purchase->getPaymentType(),
                'qr_code_url' => $qrCodeUri,
                'pay_by_card_url' => $paymentUrl
            ]);

        $this->mailer->send($email);
    }

    public function sendPaymentReceivedEmail(Purchase $purchase, string $invoicePath, string $template): void
    {
        $transportationAction = $purchase->getTransportation()->getAction()->getId();

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate($template)
            ->context(['transportation_action' => $transportationAction])
            ->attachFromPath($invoicePath, 'faktura.pdf', 'application/pdf');

        $this->mailer->send($email);
    }

    public function sendEmail(Purchase $purchase, string $template): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate($template)
            ->context([]);

        $this->mailer->send($email);
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
        ]);
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

    public function sendFreeSampleMailToInfo($formData, Product $product): void
    {
        $email = new TemplatedEmail();
        $email
            ->subject($this->translator->trans('email.free_sample.subject'))
            ->addFrom($formData['mail'])
            ->addTo($this->fromAddress);

//        TODO: make this
//        $email->htmlTemplate('mailing/free-sample.html.twig')
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

    /**
     * Sends a password reset email to the user.
     */
    public function sendPasswordResetEmail(string $recipientEmail, ResetPasswordToken $resetToken): bool
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($recipientEmail)
            ->subject($this->translator->trans('email.password_reset.subject'))
            ->htmlTemplate('reset_password/email.html.twig')
            ->context(['resetToken' => $resetToken]);

        try {
            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
//            $this->logger->error('…', ['exception' => $e]);
            return false;
        }
    }
}