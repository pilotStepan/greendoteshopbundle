<?php

namespace Greendot\EshopBundle\Service\QrPayment;

use DateTimeInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Greendot\EshopBundle\Money\Money;
use Rikudou\Iban\Iban\IBAN;
use RuntimeException;
use rikudou\SkQrPayment\QrPayment;

/**
 * Builds the Slovak PAY by square payment QR code. Encoding needs the `xz` binary (via rikudou/skqrpayment).
 *
 * The standard requires the official logo next to the code, so the code is placed inside the unmodified
 * frame artwork from SBA's logo pack (Logo_PayBySquare.zip, logo_jpg/512/pay-bottom-light.jpg).
 *
 * @see https://www.sbaonline.sk/projekt/projekty-z-oblasti-platobnych-sluzieb/
 */
class PayBySquareQrGenerator
{
    private const FRAME_PATH = __DIR__ . '/../../../resources/pay-by-square/pay-bottom-light.jpg';
    private const FRAME_INNER_OFFSET = 9;
    private const FRAME_INNER_SIZE = 494;
    private const QUIET_ZONE_MODULES = 4;
    private const CONSTANT_SYMBOL = '0308';

    public function png(
        string            $iban,
        Money             $money,
        ?int              $variableSymbol,
        DateTimeInterface $dueDate,
        string            $beneficiaryName,
    ): string {
        return $this->placeInFrame(
            $this->renderCode($this->payload($iban, $money, $variableSymbol, $dueDate, $beneficiaryName))
        );
    }

    public function payload(
        string            $iban,
        Money             $money,
        ?int              $variableSymbol,
        DateTimeInterface $dueDate,
        string            $beneficiaryName,
    ): string {
        return (new QrPayment(new IBAN($iban)))
            ->setAmount($money->value)
            ->setCurrency($money->iso)
            ->setVariableSymbol($variableSymbol)
            ->setConstantSymbol(self::CONSTANT_SYMBOL)
            ->setDueDate($dueDate)
            ->setPayeeName($beneficiaryName)
            ->getQrString();
    }

    /** Renders the code at exactly the frame's inner size, with a quiet zone of at least 4 modules. */
    private function renderCode(string $payload): string
    {
        $modules = $this->build($payload, self::FRAME_INNER_SIZE, 0)->getMatrix()->getBlockCount();
        $moduleSize = (int) floor(self::FRAME_INNER_SIZE / ($modules + 2 * self::QUIET_ZONE_MODULES));
        $codeSize = $moduleSize * $modules;
        $margin = (int) floor((self::FRAME_INNER_SIZE - $codeSize) / 2);

        return $this->build($payload, $codeSize, $margin)->getString();
    }

    private function build(string $payload, int $size, int $margin): ResultInterface
    {
        return (new Builder(
            writer: new PngWriter(),
            validateResult: false,
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: $margin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();
    }

    private function placeInFrame(string $codePng): string
    {
        $frame = imagecreatefromjpeg(self::FRAME_PATH);
        $code = imagecreatefromstring($codePng);
        if ($frame === false || $code === false) {
            throw new RuntimeException('Could not load the PAY by square frame or code image.');
        }

        imagecopy($frame, $code, self::FRAME_INNER_OFFSET, self::FRAME_INNER_OFFSET, 0, 0, imagesx($code), imagesy($code));

        ob_start();
        imagepng($frame);

        return (string) ob_get_clean();
    }
}
