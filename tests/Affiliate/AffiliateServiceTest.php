<?php

namespace Greendot\EshopBundle\Tests\Affiliate;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Greendot\EshopBundle\Affiliate\AffiliateService;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

class AffiliateServiceTest extends TestCase
{
    public function testCreateAffiliateEntryResolvesClientIdFromHashAndPassesReferer(): void
    {
        $purchase = (new Purchase())
            ->setAffiliateId('some-hash')
            ->setAdId(7)
            ->setReferer('https://www.seznam.cz/search?q=yoga');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT idklient FROM klient WHERE klic = :hash LIMIT 1', ['hash' => 'some-hash'])
            ->willReturn('42');

        $connection->expects($this->once())
            ->method('insert')
            ->with(
                'vydelky',
                $this->callback(function (array $data) {
                    $this->assertSame(42, $data['FK_idklient']);
                    $this->assertSame('https://www.seznam.cz/search?q=yoga', $data['referer']);
                    $this->assertSame(7, $data['FK_idreklama']);

                    return true;
                }),
            );

        $service = $this->createService($connection);
        $service->createAffiliateEntry($purchase);
    }

    public function testCreateAffiliateEntrySkipsInsertWhenClientHashIsUnknown(): void
    {
        $purchase = (new Purchase())->setAffiliateId('unknown-hash');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $connection->expects($this->never())->method('insert');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                $this->stringContains('unknown client hash'),
                $this->callback(function (array $context) {
                    $this->assertSame('unknown-hash', $context['affiliateId']);

                    return true;
                }),
            );

        $service = $this->createService($connection, $logger);
        $service->createAffiliateEntry($purchase);
    }

    public function testCreateAffiliateEntryTruncatesRefererToPartnerColumnWidth(): void
    {
        $purchase = (new Purchase())
            ->setAffiliateId('some-hash')
            ->setReferer(str_repeat('a', 300));

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('1');
        $connection->expects($this->once())
            ->method('insert')
            ->with(
                'vydelky',
                $this->callback(function (array $data) {
                    $this->assertSame(250, strlen($data['referer']));

                    return true;
                }),
            );

        $service = $this->createService($connection);
        $service->createAffiliateEntry($purchase);
    }

    private function createService(Connection $connection, ?LoggerInterface $logger = null): AffiliateService
    {
        $currency = new Currency();

        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $currencyRepository->method('findOneBy')->willReturn($currency);

        $priceCalculator = $this->createMock(PurchasePrice::class);
        $priceCalculator->method('getPrice')->willReturn(1000.0);

        $priceFactory = $this->createMock(PurchasePriceFactory::class);
        $priceFactory->method('create')->willReturn($priceCalculator);

        return new AffiliateService(
            $this->createMock(RequestStack::class),
            $priceFactory,
            $currencyRepository,
            $this->createMock(MessageBusInterface::class),
            $logger ?? $this->createMock(LoggerInterface::class),
            $connection,
        );
    }
}
