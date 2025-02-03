<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\PaymentRepository;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\LocaleAwareInterface;

class ManageMails
{
    private readonly Address $fromAddress;

    public function __construct(
        private readonly MailerInterface          $mailer,
        private readonly ManagePurchase           $manageOrder,
        private readonly PriceCalculator          $priceCalculator,
        private readonly CurrencyRepository       $currencyRepository,
        private readonly LocaleAwareInterface     $localeAware,
        private readonly RequestStack             $requestStack,
        private readonly ManagerRegistry          $managerRegistry,
        private readonly PaymentTypeRepository    $paymentTypeRepository,
        private readonly PaymentRepository        $paymentRepository,
        private readonly TransportationRepository $transportationRepository,
        private readonly QRcodeGenerator          $qrCodeGenerator,
        private readonly GPWebpay                 $webpay,
    )
    {
        $this->fromAddress = new Address('info@bdl.cz', 'BDL');
    }


    public function mailMessage(
        string      $receivingEmail,
        string      $content = "",
        string      $headline = "",
        string      $subject = '',
        string|null $link = null,
                    $buttonName = "Odkaz"
    ): void
    {
        $email = new TemplatedEmail();
        $email
            ->subject($subject)
            ->addFrom($this->fromAddress)
            ->addTo($receivingEmail);
        //->addCc('');
        if ($link) {
            $email->htmlTemplate('mailing/base.html.twig')
                ->context([
                    'headline'    => $headline,
                    'content'     => $content,
                    'href'        => $link,
                    'button_name' => $buttonName
                ]);
        } else {
            $email->htmlTemplate('mailing/base.html.twig')
                ->context([
                    'headline' => $headline,
                    'content'  => $content,
                ]);
        }

        try {
            $this->mailer->send($email);
        } catch (\Exception $exception) {
            dd($exception);
        }
    }

    public function sendOrderReceiveEmail(Purchase $purchase, float $purchasePrice, string $template): void
    {
        $varSymbol  = $this->paymentRepository->findByPurchaseId($purchase->getId());
        $dueDate    = new \DateTime('+14 days');
        $qrCodeUri  = $this->qrCodeGenerator->getUri($purchase, $dueDate, $purchasePrice);
        $paymentUrl = $this->webpay->getPayLink($purchase, $varSymbol, $purchasePrice);

        $email = (new TemplatedEmail())
            ->from('info@greendot.com')
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate($template)
            ->context([
                'purchase_price'  => $purchasePrice,
                'var_symbol'      => $varSymbol,
                'bank_account'    => $purchase->getPaymentType()->getAccount(),
                'payment_type'    => $purchase->getPaymentType(),
                'qr_code_url'     => $qrCodeUri,
                'pay_by_card_url' => $paymentUrl
            ]);

        $this->mailer->send($email);
    }

    public function sendPaymentReceivedEmail(Purchase $purchase, string $invoicePath, string $template): void
    {
        $transportationAction = $purchase->getTransportation()->getAction()->getId();

        $email = (new TemplatedEmail())
            ->from('info@greendot.com')
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate($template)
            ->context([
                'transportation_action' => $transportationAction
            ])
            ->attachFromPath($invoicePath, 'faktura.pdf', 'application/pdf');

        $this->mailer->send($email);
    }

    public function sendNotReceivedEmail(Purchase $purchase, string $template): void
    {
        $email = (new TemplatedEmail())
            ->from('info@greendot.com')
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate($template)
            ->context([]);

        $this->mailer->send($email);
    }

    public function sendEmail(Purchase $purchase, string $template): void
    {
        $email = (new TemplatedEmail())
            ->from('info@greendot.com')
            ->to($purchase->getClient()->getMail())
            ->subject($this->getEmailSubject($purchase))
            ->htmlTemplate($template)
            ->context([]);

        $this->mailer->send($email);
    }

    private function getEmailSubject(Purchase $purchase): string
    {
        return match ($purchase->getState()) {
            'created'          => 'Objednávka ' . $purchase->getId() . ' byla přijata',
            'paid'             => 'Platba za objednávku ' . $purchase->getId() . ' byla přijata',
            'not_paid'         => 'Platba za objednávku ' . $purchase->getId() . ' nebyla přijata',
            'sent'             => 'Objednávka ' . $purchase->getId() . ' byla odeslána',
            'ready_for_pickup' => 'Objednávka ' . $purchase->getId() . ' je připravena k vyzvednutí',
            'picked_up'        => 'Objednávka ' . $purchase->getId() . ' byla vyzvednuta',
            'canceled'         => 'Objednávka ' . $purchase->getId() . ' byla zrušena',
            default            => 'Informace o objednávce ' . $purchase->getId(),
        };
    }

    private function setLocaleAndRefreshEntities(Purchase $purchase): void
    {
        if (!$this->requestStack->getCurrentRequest() || !$this->requestStack->getCurrentRequest()->getLocale()) {
            $this->localeAware->setLocale('cs');

            $entityManager = $this->managerRegistry->getManager();

            $transportation = $this->refreshEntity($purchase->getTransportation(), 'cs');
            $payment        = $this->refreshEntity($purchase->getPaymentType(), 'cs');

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

    private function createEmail(Purchase $purchase, SessionInterface $session, PriceCalculator $priceCalculator): TemplatedEmail
    {
        $currency = $session->get('selectedCurrency');

        $subject               = $this->getSubject($purchase);
        $productVariantsInCart = $this->getProductVariantsCount($purchase);

        $purchasePrice = $priceCalculator->calculatePurchasePrice(
            $purchase,
            $currency,
            VatCalculationType::WithVAT,
            1,
            DiscountCalculationType::WithDiscount,
            true,
            VoucherCalculationType::WithoutVoucher,
            true
        );

        return (new TemplatedEmail())
            ->from($this->fromAddress)
            ->subject($subject)
            ->htmlTemplate('mailing/base.html.twig')
            ->context([
                'headline'                    => $subject,
                'content'                     => "",
                'product_variant_occurrences' => $productVariantsInCart,
                'purchase_price'              => $purchasePrice,
                'order'                       => $purchase,
                'currency_repository_id'      => 1
            ]);
    }

    private function getProductVariantsCount(Purchase $purchase): array
    {
        $productVariantsInCart = [];
        foreach ($purchase->getProductVariants() as $orderProductVariant) {
            $productVariantId                         = $orderProductVariant->getProductVariant()->getId();
            $productVariantsInCart[$productVariantId] = ($productVariantsInCart[$productVariantId] ?? 0) + 1;
        }
        return $productVariantsInCart;
    }

    private function addRecipients(TemplatedEmail $email, Purchase $purchase, bool $ccSender): void
    {
        $email->to($purchase->getClient()->getMail());

        $notifyMail = $this->manageOrder->getNotifyEmail($purchase);
        if ($notifyMail) {
            $email->addCc($notifyMail);
        }

        if ($ccSender) {
            $email->addCc($this->fromAddress->getAddress());
        }

        $email->addCc("matyas@greendot.cz");
    }
}