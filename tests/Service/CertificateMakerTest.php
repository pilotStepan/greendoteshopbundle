<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Service;

use Twig\Environment;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\ConversionRate;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\CertificateMaker;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ConversionRateRepository;

/**
 * The voucher must be rendered in the client's locale (same as purchase notifications),
 * not in whatever locale the current request (e.g. the admin) happens to be in, and show
 * its amount in the currency it was bought in.
 */
class CertificateMakerTest extends TestCase
{
    public function testRendersUnderTheClientsLocaleAndRestoresLocaleAfter(): void
    {
        $localeSwitcher = new LocaleSwitcher('cs', []);

        $purchase = (new Purchase())->setClient((new Client())->setLocale('sk'));
        $voucher = (new Voucher())->setHash('abcdef123456')->setPurchaseIssued($purchase);

        [$maker, $rendered] = $this->buildMaker($localeSwitcher);

        $maker->renderVoucherHtml($voucher);

        $this->assertSame('sk', $rendered->locale, 'voucher must be rendered under the client locale');
        $this->assertSame('cs', $localeSwitcher->getLocale(), 'locale must be restored after rendering');
    }

    public function testVoucherWithoutPurchaseKeepsTheCurrentLocale(): void
    {
        $localeSwitcher = new LocaleSwitcher('sk', []);

        [$maker, $rendered] = $this->buildMaker($localeSwitcher);

        $maker->renderVoucherHtml((new Voucher())->setHash('abcdef123456'));

        $this->assertSame('sk', $rendered->locale);
    }

    public function testAmountIsConvertedToThePurchaseCurrency(): void
    {
        $eur = (new Currency())->setName('EUR')->setRounding(1);

        $purchase = (new Purchase())->setClient(new Client())->setCurrency($eur);
        $voucher = (new Voucher())->setHash('abcdef123456')->setAmount(1000)->setPurchaseIssued($purchase);

        [$maker, $rendered] = $this->buildMaker(new LocaleSwitcher('cs', []), rate: 0.04);

        $maker->renderVoucherHtml($voucher);

        $this->assertEquals(new Money(40.0, 'EUR'), $rendered->context['amountMoney']);
        $this->assertSame(1000, $rendered->context['amount'], 'original amount is kept for the design tiers');
    }

    /**
     * @return array{CertificateMaker, object{locale: ?string, context: array}}
     */
    private function buildMaker(LocaleSwitcher $localeSwitcher, float $rate = 1.0): array
    {
        $rendered = new class {
            public ?string $locale = null;
            public array $context = [];
        };

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(
            function (string $template, array $context) use ($rendered, $localeSwitcher) {
                $rendered->locale = $localeSwitcher->getLocale();
                $rendered->context = $context;
                return '<html></html>';
            },
        );

        $czk = (new Currency())->setName('CZK')->setRounding(0)->setIsDefault(true);
        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $currencyRepository->method('findOneBy')->willReturn($czk);

        $conversionRateRepository = $this->createMock(ConversionRateRepository::class);
        $conversionRateRepository->method('getByDate')->willReturnCallback(
            fn (Currency $currency) => (new ConversionRate())->setCurrency($currency)->setRate($rate),
        );

        $maker = new CertificateMaker(
            $twig,
            new ParameterBag(),
            new PurchaseLocaleResolver('cs'),
            $localeSwitcher,
            new CurrencyManager(new RequestStack(), $currencyRepository),
            new PriceUtils($conversionRateRepository),
        );

        return [$maker, $rendered];
    }
}
