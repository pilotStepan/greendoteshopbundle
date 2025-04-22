<?php


namespace Greendot\EshopBundle\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;

class CertificateMaker
{
    private const PDF_OUTPUT_DIR = '/public/receipts/';
    private const EXCEL_OUTPUT_DIR = 'receipts/';
    private const VAT_RATES = [10, 15, 21];

    public function __construct(
        private readonly Environment             $twig,
        private readonly ContainerInterface      $container,
        private readonly SettingsRepository      $settingsRepository,
        private readonly ValueAddedTaxCalculator $valueAddedTaxCalculator,
    )
    {
    }

    public function createCertificate(Voucher $voucher): ?string
    {
        $certificateData = $this->prepareCertificateData($voucher);

        if (!$certificateData) {
            return null;
        }

        $html = $this->renderHtml($certificateData);

        $pdfFilePath = $this->generatePdf($html, $certificateData['id']);

        return $pdfFilePath;
    }

    private function prepareCertificateData(Voucher $voucher): ?array
    {
        return [
            'id' => $voucher->getId(),
            'amount' => $voucher->getId(),
            'validFrom' => $voucher->getDateIssued(),
            'validUntil' => $voucher->getDateUntil(),
            'type' => $voucher->getType(),
            'purchaseIssued' => $voucher->getPurchaseIssued(),
            'clientIssued' => $voucher->getPurchaseIssued()->getClient(),
        ];
    }

    private function renderHtml(array $data): string
    {
        $template = 'pdf/voucher.html.twig';
        return $this->twig->render($template, $data);
    }

    private function generatePdf(string $html, int $certificateId): string
    {
        $pdfOptions = new Options();
        $pdfOptions->set('isRemoteEnabled', true);
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->setChroot(realpath('build'));
        $dompdf = new Dompdf($pdfOptions);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
