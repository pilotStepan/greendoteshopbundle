<?php


namespace Greendot\EshopBundle\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class CertificateMaker
{
    public function __construct(
        private Environment $twig,
        private ParameterBagInterface $parameterBag,
    ) {}

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
        $certificateData = $this->prepareCertificateData($voucher);
        return $this->renderHtml($certificateData);
    }
}
