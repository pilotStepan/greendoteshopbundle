<?php


namespace Greendot\EshopBundle\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use Greendot\EshopBundle\Entity\Project\Voucher;

readonly class CertificateMaker
{
    public function __construct(private Environment $twig) {}

    public function createCertificate(Voucher $voucher): string
    {
        $certificateData = $this->prepareCertificateData($voucher);
        $html = $this->renderHtml($certificateData);
        return $this->generatePdf($html);
    }

    private function prepareCertificateData(Voucher $voucher): array
    {
        return [
            'id' => $voucher->getId(),
            'amount' => $voucher->getAmount(),
            'hash' => $voucher->getHash(),
            'validFrom' => $voucher->getDateIssued(),
            'validUntil' => $voucher->getDateUntil(),
            'type' => $voucher->getType(),
            'purchaseIssued' => $voucher->getPurchaseIssued(),
            'clientName' => $voucher->getPurchaseIssued()?->getClient()->getFullname(),
        ];
    }

    private function renderHtml(array $data): string
    {
        $template = 'pdf/voucher.html.twig';
        return $this->twig->render($template, $data);
    }

    private function generatePdf(string $html): string
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

    /* Used on the project side */
    public function renderVoucherHtml(Voucher $voucher): string
    {
        $certificateData = $this->prepareCertificateData($voucher);
        return $this->renderHtml($certificateData);
    }
}
