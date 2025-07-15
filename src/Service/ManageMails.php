<?php

namespace Greendot\EshopBundle\Service;

use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Mail\Factory\OrderDataFactory;
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
        private OrderDataFactory     $dataFactory,
        private string               $fromEmail,
        private string               $fromName,
    )
    {
        $this->fromAddress = new Address($this->fromEmail, $this->fromName);
    }

    public function sendOrderReceiveEmail(Purchase $purchase): void
    {
        $orderData = $this->dataFactory->create($purchase);

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate('email/order/receive.html.twig')
            ->context(['data' => $orderData]);

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

    public function sendFreeSampleMailToInfo($formData, Product $product): void
    {
        $email = new TemplatedEmail();
        $email
            ->subject($this->translator->trans('email.free_sample.subject', [], 'emails'))
            ->addFrom($formData['mail'])
            ->addTo($this->fromAddress);

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
}