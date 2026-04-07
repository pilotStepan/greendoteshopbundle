<?php

namespace Greendot\EshopBundle\Service;

use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use Greendot\EshopBundle\Message\Notification\IssuedVoucherEmail;

readonly class ManageMails
{
    private Address $fromAddress;

    public function __construct(
        private MailerInterface      $mailer,
        private TranslatorInterface  $translator,
        private CertificateMaker     $certificateMaker,
        private InvoiceMaker         $invoiceMaker,
        private string               $fromEmail,
        private string               $fromName,
        private PurchaseUrlGenerator $purchaseUrlGenerator,
        private MessageBusInterface  $messageBus,
    )
    {
        $this->fromAddress = new Address($this->fromEmail, $this->fromName);
    }

    public function getBaseTemplate(): TemplatedEmail
    {
        return (new TemplatedEmail())->from($this->fromAddress);
    }

    public function sendTemplate(TemplatedEmail $templatedEmail): void
    {
        $this->mailer->send($templatedEmail);
    }

    /**
     * Sends a password reset email to the user.
     */
    public function sendPasswordResetEmail(string $recipientEmail, ResetPasswordToken $resetToken): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($recipientEmail)
            ->subject($this->translator->trans('Žádost o obnovu hesla'))
            ->htmlTemplate('email/auth/password_reset.html.twig')
            ->context(['resetToken' => $resetToken])
        ;

        $this->mailer->send($email);
    }

    public function sendPurchaseDiscussionEmail(Purchase $purchase): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($this->translator->trans('email.subject.purchase_discussion', ['%id%' => $purchase->getId()], 'emails'))
            ->htmlTemplate('email/purchase-discussion/new_discussion.html.twig')
            ->context([
                'purchase_id' => $purchase->getId(),
                'client_section_link' => $this->purchaseUrlGenerator->buildOrderDetailUrl($purchase),
                'last_admin_message' => $purchase->getLastAdminMessage(),
            ])
        ;

        $this->mailer->send($email);
    }

    public function sendWishlistEmail(string $recipientEmail, Purchase $wishlist): void
    {
        $clientName = $wishlist->getClient()->getName();
        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($recipientEmail)
            ->subject($this->translator->trans('email.subject.wishlist', ['%name%' => $clientName], 'emails'))
            ->htmlTemplate('email/wishlist/wishlist.html.twig')
            ->context(['data' => [
                'clientName' => $clientName,
                'url' => $this->purchaseUrlGenerator->buildSharedWishlistUrl($wishlist),
            ]])
        ;

        $this->mailer->send($email);
    }

    public function sendIssuedVoucherEmail(Voucher $voucher): void
    {
        $recipientEmail = $voucher->getPurchaseIssued()?->getClient()?->getMail();
        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($recipientEmail)
            ->subject($this->translator->trans('email.subject.purchase_voucher', [], 'emails'))
            ->htmlTemplate('email/voucher/voucher.html.twig')
        ;

        $pdfContent = $this->certificateMaker->createCertificate($voucher);
        $email->attach(
            $pdfContent,
            'voucher_' . $voucher->getId() . '.pdf',
            'application/pdf',
        );
        $this->mailer->send($email);
    }
}
