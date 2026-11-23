<?php

namespace Greendot\EshopBundle\Tests\Service\QrPayment;

use DateTimeImmutable;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Service\QrPayment\PayBySquareQrGenerator;
use PHPUnit\Framework\TestCase;

class PayBySquareQrGeneratorTest extends TestCase
{
    private const IBAN = 'CZ3620100000002400327394';

    public function testPayloadDecodesToThePaymentDetails(): void
    {
        $fields = $this->decode((new PayBySquareQrGenerator())->payload(
            self::IBAN,
            new Money(12.5, 'EUR'),
            13571,
            new DateTimeImmutable('2026-11-06'),
            'Jana Smažíková',
        ));

        self::assertSame('12.5', $fields['amount']);
        self::assertSame('EUR', $fields['currency']);
        self::assertSame('20261106', $fields['dueDate']);
        self::assertSame('13571', $fields['variableSymbol']);
        self::assertSame('0308', $fields['constantSymbol']);
        self::assertSame(self::IBAN, $fields['iban']);
        self::assertSame('FIOBCZPP', $fields['bic']);
        self::assertSame('Jana Smažíková', $fields['beneficiaryName']);
    }

    public function testPayloadAllowsMissingVariableSymbol(): void
    {
        $fields = $this->decode((new PayBySquareQrGenerator())->payload(
            self::IBAN,
            new Money(1.0, 'EUR'),
            null,
            new DateTimeImmutable('2026-11-06'),
            'Holder',
        ));

        self::assertSame('', $fields['variableSymbol']);
    }

    public function testPngIsTheFramedCode(): void
    {
        $png = (new PayBySquareQrGenerator())->png(
            self::IBAN,
            new Money(12.5, 'EUR'),
            13571,
            new DateTimeImmutable('2026-11-06'),
            'Holder',
        );

        $size = getimagesizefromstring($png);
        self::assertSame(IMAGETYPE_PNG, $size[2]);
        self::assertSame([512, 600], [$size[0], $size[1]]);
    }

    /**
     * Reverses the spec's encoding: base32hex, 2-byte header + 2-byte length, raw LZMA1, CRC32 + tab-separated data.
     *
     * @return array<string, string>
     */
    private function decode(string $payload): array
    {
        $bits = '';
        foreach (str_split($payload) as $char) {
            $bits .= str_pad(decbin(strpos('0123456789ABCDEFGHIJKLMNOPQRSTUV', $char)), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split(substr($bits, 0, strlen($bits) - strlen($bits) % 8), 8) as $byte) {
            $bytes .= chr(bindec($byte));
        }

        self::assertSame("\x00\x00", substr($bytes, 0, 2), 'PAY by square type, version and document type');
        $length = unpack('v', substr($bytes, 2, 2))[1];

        $process = proc_open('xz --format=raw --lzma1=lc=3,lp=0,pb=2,dict=128KiB -dc -', [['pipe', 'r'], ['pipe', 'w'], ['file', '/dev/null', 'w']], $pipes);
        fwrite($pipes[0], substr($bytes, 4));
        fclose($pipes[0]);
        $decompressed = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);

        $decompressed = substr($decompressed, 0, $length);
        $data = substr($decompressed, 4);
        self::assertSame(hash('crc32b', $data, true), strrev(substr($decompressed, 0, 4)), 'CRC32 of the data');

        $values = explode("\t", $data);

        return [
            'amount' => $values[3],
            'currency' => $values[4],
            'dueDate' => $values[5],
            'variableSymbol' => $values[6],
            'constantSymbol' => $values[7],
            'iban' => $values[12],
            'bic' => $values[13],
            'beneficiaryName' => $values[16],
        ];
    }
}
