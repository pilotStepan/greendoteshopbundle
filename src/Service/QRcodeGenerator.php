<?php

namespace Greendot\EshopBundle\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class QRcodeGenerator
{
    public function __construct(
        private Filesystem              $filesystem,
        private RequestStack            $requestStack,
        private UrlGeneratorInterface   $router,
        private CurrencyManager         $currencyManager, 
        private ManagePurchase          $managePurchase,
        #[Autowire('%kernel.project_dir%')]
        private string                  $projectDir,
        #[Autowire('%env(APP_URL)%')]
        private string                  $appUrl = '',
    )
    { }

    public function getUri(Purchase $purchase): string
    {
        $iban = $purchase->getPaymentType()->getIban();
        if (!$iban) throw new Exception('Missing IBAN in paymentType id'.$purchase->getPaymentType()->getId());

        $this->managePurchase->preparePrices($purchase);

        $now = new \DateTimeImmutable('now');
        $currency = $this->currencyManager->get();

        $qrContent = 'SPD*1.0*ACC:'.$iban.'*AM:' .
            number_format($purchase->getTotalPrice(), 2, '.', '') .
            '*CC:' . $currency->getName() . '*DT:' . $now->format("Ymd") .
            '*X-VS:' . $purchase->getId().
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
        $result = $builder->build();

        $filePath = sprintf('QRcodes/qr_code_%s.png', $purchase->getId());
        $fullPath = $this->projectDir . '/public/' . $filePath;

        $this->filesystem->dumpFile($fullPath, $result->getString());

        return '/' . $filePath;
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