<?php

namespace Greendot\EshopBundle\Service\PaymentGateway;

use Alcohol\ISO4217;
use Doctrine\ORM\EntityManagerInterface;
use Granam\GpWebPay\CardPayRequest;
use Granam\GpWebPay\CardPayRequestValues;
use Granam\GpWebPay\CardPayResponse;
use Granam\GpWebPay\Codes\CurrencyCodes;
use Granam\GpWebPay\DigestSigner;
use Granam\GpWebPay\Settings;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

//    For tests against testing payment gateway, you can use payment card.
//    Card number: 4056070000000008
//    Card validity: 12/2025
//    CVC2: 200
//    3D Secure password: password

readonly class GPWebpay implements PaymentGatewayInterface
{
    public static function action(): PaymentTechnicalAction
    {
        return PaymentTechnicalAction::GLOBAL_PAYMENTS;
    }

    public function __construct(
        private string                 $private_key,
        private string                 $public_key,
        private string                 $private_pass,
        private string                 $merchant_id,
        private UrlGeneratorInterface  $urlGenerator,
        private EntityManagerInterface $entityManager
    ) {}

    public function getPayLink(Purchase $purchase): string
    {
        $payment = new Payment();
        $payment->setDate(new \DateTime());
        $payment->setPurchase($purchase);
        $payment->setExternalId(1);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $settings = Settings::createForTest(
            $this->private_key,
            $this->private_pass,
            $this->public_key,
            $this->merchant_id,
            $this->urlGenerator->generate(
                'shop_order_verify',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
        );
        $currencyCodes = new CurrencyCodes(new ISO4217());
        $digestSigner = new DigestSigner($settings);
        $requestValues = CardPayRequestValues::createFromArray([
            'ORDERNUMBER' => $payment->getId(),
            'AMOUNT'      => 1, // FIXME: $purchase->getTotalPrice(),
            'CURRENCY'    => '203',
            'DEPOSITFLAG' => true,
            'MERORDERNUM' => $purchase->getId(),
        ], $currencyCodes);
        $cardPayRequest = new CardPayRequest($requestValues, $settings, $digestSigner);
        return $cardPayRequest->getRequestUrlWithGetParameters();
    }

    public function verifyLink(): CardPayResponse
    {
        $settings = Settings::createForTest(
            $this->private_key,
            $this->private_pass,
            $this->public_key,
            $this->merchant_id,
            $this->urlGenerator->generate(
                'shop_order_verify',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
        );

        $currencyCodes = new CurrencyCodes(new ISO4217());
        $digestSigner  = new DigestSigner($settings);

        return CardPayResponse::createFromArray($_GET, $settings, $digestSigner);
    }
}