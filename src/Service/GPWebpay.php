<?php

namespace Greendot\EshopBundle\Service;

use Alcohol\ISO4217;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Doctrine\ORM\EntityManagerInterface;
use Granam\GpWebPay\CardPayRequest;
use Granam\GpWebPay\CardPayRequestValues;
use Granam\GpWebPay\CardPayResponse;
use Granam\GpWebPay\Codes\CurrencyCodes;
use Granam\GpWebPay\DigestSigner;
use Granam\GpWebPay\Settings;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

//    For tests against testing payment gateway you can use payment card
//    Card number: 4056070000000008
//    Card validity: 12/2020
//    CVC2: 200
//    3D Secure password: password

class GPWebpay
{
    private $private_key;
    private $public_key;
    private $private_pass;
    private $merchant_id;
    private $urlGenerator;
    private $entityManager;

    public function __construct($private_key, $public_key, $private_pass, $merchant_id, UrlGeneratorInterface $urlGenerator, EntityManagerInterface $entityManager)
    {
        $this->private_key   = $private_key;
        $this->public_key    = $public_key;
        $this->private_pass  = $private_pass;
        $this->merchant_id   = $merchant_id;
        $this->urlGenerator  = $urlGenerator;
        $this->entityManager = $entityManager;
    }

    public function getPayLink(Purchase $purchase, int $totalPrice): string
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
            'AMOUNT'      => $totalPrice,
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