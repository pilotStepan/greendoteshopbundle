<?php


namespace Greendot\EshopBundle\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class CertificateMaker
{
    public function __construct(
        private Environment $twig,
        private ParameterBagInterface $parameterBag,
        private PurchaseLocaleResolver $purchaseLocaleResolver,
        private LocaleSwitcher $localeSwitcher,
        private CurrencyManager $currencyManager,
        private PriceUtils $priceUtils,
    ) {}

    public function createCertificate(Voucher $voucher): string
    {
        return $this->generatePdf($this->renderHtml($voucher));
    }

    private function prepareCertificateData(Voucher $voucher): array
    {
        return [
            'id' => $voucher->getId(),
            'amount' => $voucher->getAmount(),
            'amountMoney' => $this->resolveAmountMoney($voucher),
            'hash' => $voucher->getHash(),
            'validFrom' => $voucher->getDateIssued(),
            'validUntil' => $voucher->getDateUntil(),
            'type' => $voucher->getType(),
            'purchaseIssued' => $voucher->getPurchaseIssued(),
            'clientName' => $voucher->getPurchaseIssued()?->getClient()->getFullname(),
        ];
    }

    private function resolveAmountMoney(Voucher $voucher): ?Money
    {
        if ($voucher->getAmount() === null) {
            return null;
        }

        $purchase = $voucher->getPurchaseIssued();
        $currency = $purchase
            ? $this->currencyManager->getForPurchase($purchase)
            : $this->currencyManager->get();
        $conversionRate = $this->priceUtils->getConversionRate($currency, $purchase);

        return Money::fromCurrency(
            $this->priceUtils->convertCurrency($voucher->getAmount(), $conversionRate),
            $currency,
        );
    }

    private function resolveLocale(Voucher $voucher): string
    {
        $purchase = $voucher->getPurchaseIssued();

        return $purchase
            ? $this->purchaseLocaleResolver->resolve($purchase)
            : $this->localeSwitcher->getLocale();
    }

    private function renderHtml(Voucher $voucher): string
    {
        $template = 'pdf/voucher.html.twig';
        return $this->localeSwitcher->runWithLocale(
            $this->resolveLocale($voucher),
            fn () => $this->twig->render($template, $this->prepareCertificateData($voucher)),
        );
    }

    private function generatePdf(string $html): string
    {
        $publicDir = $this->parameterBag->get('kernel.project_dir') . '/public';

        $pdfOptions = new Options();
        $pdfOptions->set('isRemoteEnabled', true);
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('chroot', realpath($publicDir));
        $pdfOptions->set('fontDir', $publicDir . '/build/fonts');
        $pdfOptions->set('fontCache', $publicDir . '/build/fonts');

        $dompdf = new Dompdf($pdfOptions);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->setBasePath($publicDir);
        $dompdf->render();

        return $dompdf->output();
    }

    /* Used on the project side */
    public function renderVoucherHtml(Voucher $voucher): string
    {
        return $this->renderHtml($voucher);
    }
}
