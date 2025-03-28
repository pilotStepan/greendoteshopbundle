<?php

namespace Greendot\EshopBundle\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\Filesystem\Filesystem;

class QRcodeGenerator
{
    private Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getUri($purchase, \DateTimeInterface $dueDate, float $totalAmount): string
    {
        $qrContent = 'SPD*1.0*ACC:CZ1020100000002802559702*AM:' .
            number_format($totalAmount, 2, '.', '') .
            '*CC:CZK*DT:' . $dueDate->format("Y.m.d") .
            '*X-VS:' . $purchase->getInvoiceNumber();

        $result = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $qrContent,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );
        $result = $result->build();

        $filePath = sprintf('QRcodes/qr_code_%s.png', $purchase->getId());
        $fullPath = 'public/' . $filePath;

        $this->filesystem->dumpFile($fullPath, $result->getString());

        return '/' . $filePath;
    }
}