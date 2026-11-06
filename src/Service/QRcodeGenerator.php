<?php

namespace Greendot\EshopBundle\Service;

use DateTimeImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Money\Exception\PurchaseCurrencyMismatchException;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Service\QrPayment\PayBySquareQrGenerator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

class QRcodeGenerator
{
    public function __construct(
        private Filesystem              $filesystem,
        private RequestStack            $requestStack,
        private UrlGeneratorInterface   $router,
        private CurrencyManager         $currencyManager,
        private ManagePurchase          $managePurchase,
        private PayBySquareQrGenerator  $payBySquareQrGenerator,
        private LoggerInterface         $logger,
        #[Autowire('%kernel.project_dir%')]
        private string                  $projectDir,
        #[Autowire(param: 'greendot_eshop.payment.pay_by_square.beneficiary_name')]
        private string                  $payBySquareBeneficiaryName,
        #[Autowire('%env(APP_URL)%')]
        private string                  $appUrl = '',
    ) {}

    public function getUri(Purchase $purchase): string
    {
        $paymentType = $purchase->getPaymentType();
        if (!$paymentType) {
            throw new RuntimeException('Missing PaymentType on purchase ' . $purchase->getId());
        }

        $iban = $paymentType->getIban();
        if (!$iban) {
            throw new \RuntimeException('Missing IBAN in paymentType id' . $paymentType->getId());
        }

        if ($paymentType->getCurrency() !== null && $purchase->getCurrency() !== null
            && $paymentType->getCurrency() !== $purchase->getCurrency()
        ) {
            throw PurchaseCurrencyMismatchException::forPurchase($purchase);
        }

        $this->managePurchase->preparePrices($purchase);

        $now = new DateTimeImmutable('now');
        $currency = $this->currencyManager->getForPurchase($purchase);
        $money = $purchase->getTotalMoney() ?? Money::fromCurrency($purchase->getTotalPrice(), $currency);

        $png = $paymentType->getCountry() === 'SK' && $this->payBySquareBeneficiaryName !== ''
            ? $this->buildPayBySquarePng($purchase, $iban, $money, $now)
            : null;
        $png ??= $this->buildSpaydPng($purchase, $iban, $money, $now);

        $filePath = sprintf('QRcodes/qr_code_%s.png', $purchase->getId());
        $fullPath = $this->projectDir . '/public/' . $filePath;

        $this->filesystem->dumpFile($fullPath, $png);

        return '/' . $filePath;
    }

    /**
     * Returns null on failure (e.g. no xz binary): the order pages call getUri() unguarded,
     * so an exception here would take them down for a code the customer may not even need.
     */
    private function buildPayBySquarePng(Purchase $purchase, string $iban, Money $money, DateTimeImmutable $now): ?string
    {
        try {
            return $this->payBySquareQrGenerator->png($iban, $money, $purchase->getOrderNumber(), $now, $this->payBySquareBeneficiaryName);
        } catch (Throwable $e) {
            $this->logger->error('Failed to generate PAY by square QR code for purchase {purchaseId}, falling back to SPAYD', [
                'purchaseId' => $purchase->getId(),
                'exception' => $e,
            ]);

            return null;
        }
    }

    private function buildSpaydPng(Purchase $purchase, string $iban, Money $money, DateTimeImmutable $now): string
    {
        $qrContent = 'SPD*1.0*ACC:'.$iban.'*AM:' .
            number_format($money->value, 2, '.', '') .
            '*CC:' . $money->iso . '*DT:' . $now->format("Ymd") .
            '*X-VS:' . $purchase->getOrderNumber().
            '*X-KS:308';


        $builderParams = [
            'writer' => new PngWriter(),
            'writerOptions' => [],
            'validateResult' => false,
            'data' => $qrContent,
            'encoding' => new Encoding('UTF-8'),
            'errorCorrectionLevel' => ErrorCorrectionLevel::High,
            'size' => 300,
            'margin' => 10,
            'roundBlockSizeMode' => RoundBlockSizeMode::Margin,
        ];

        $logoPath = $this->projectDir . '/public/build/img/logo_qr.jpg';
        if (file_exists($logoPath)) {
            $builderParams['logoPath'] = $logoPath;
            $builderParams['logoResizeToWidth'] = 100;
        }

        $builder = new Builder(...$builderParams);

        return $builder->build()->getString();
    }


    public function getFullUrl(Purchase $purchase): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request) {
            $domain = $request->getSchemeAndHttpHost();
        } else {
            $context = $this->router->getContext();
            $host = $context->getHost();
            $domain = $host
                ? $context->getScheme() . '://' . $host
                : rtrim($this->appUrl, '/');
        }

        return $domain . $this->getUri($purchase);
    }
}