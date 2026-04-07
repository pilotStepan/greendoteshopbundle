<?php

namespace Greendot\EshopBundle\Service;

use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ManageMails
{
    private Address $fromAddress;

    public function __construct(
        private MailerInterface      $mailer,
        private TranslatorInterface  $translator,
        private CertificateMaker     $certificateMaker,
        private string               $fromEmail,
        private string               $fromName,
        private PurchaseUrlGenerator $purchaseUrlGenerator,
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

    public function sendPurchaseDiscussionEmail(Purchase $purchase): void
    {
        $subject = sprintf('%s #%d',
            $this->translator->trans('Nová odpověď v konverzaci k objednávce'),
            $purchase->getId())
        ;

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($purchase->getClient()->getMail())
            ->subject($subject)
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
        $subject = sprintf('%s %s',
            $clientName,
            $this->translator->trans('sdílí svůj seznam přání s Vámi'),
        );

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($recipientEmail)
            ->subject($subject)
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
        $recipientEmail = $voucher->getPurchaseIssued()->getClient()->getMail();

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($recipientEmail)
            ->subject($this->translator->trans('Váš dárkový poukaz'))
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
