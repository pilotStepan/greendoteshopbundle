<?php

namespace Greendot\EshopBundle\Tests\Parcel\MessageHandler;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Parcel\ParcelServiceInterface;
use Greendot\EshopBundle\Parcel\ParcelServiceProviderInterface;
use Greendot\EshopBundle\Parcel\Exception\ParcelServiceNotFoundException;
use Greendot\EshopBundle\Parcel\Message\CreateParcelMessage;
use Greendot\EshopBundle\Parcel\Message\UpdateDeliveryStatusMessage;
use Greendot\EshopBundle\Parcel\MessageHandler\CreateParcelHandler;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Repository\Project\TransportationEventRepository;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class CreateParcelHandlerTest extends TestCase
{
    private PurchaseRepository $purchaseRepo;
    private ParcelServiceProviderInterface $provider;
    private TransportationEventRepository $transportationEventRepo;
    private EntityManagerInterface $em;
    private MessageBusInterface $bus;
    private CreateParcelHandler $handler;

    protected function setUp(): void
    {
        $this->purchaseRepo            = $this->createMock(PurchaseRepository::class);
        $this->provider                = $this->createMock(ParcelServiceProviderInterface::class);
        $this->transportationEventRepo = $this->createMock(TransportationEventRepository::class);
        $this->em                      = $this->createMock(EntityManagerInterface::class);
        $this->bus                     = $this->createMock(MessageBusInterface::class);

        $this->handler = new CreateParcelHandler(
            $this->provider,
            $this->purchaseRepo,
            $this->transportationEventRepo,
            $this->em,
            $this->bus,
            new NullLogger(),
        );
    }

    private function makePurchase(?string $transportNumber = null): Purchase
    {
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getId')->willReturn(42);
        $purchase->method('getTransportNumber')->willReturn($transportNumber);
        return $purchase;
    }

    public function testPurchaseNotFound_throwsUnrecoverable(): void
    {
        $this->purchaseRepo->method('find')->willReturn(null);
        $this->expectException(UnrecoverableMessageHandlingException::class);
        ($this->handler)(new CreateParcelMessage(42));
    }

    public function testAlreadyHasTransportNumber_skipsCreateAndSchedulesStatusCheck(): void
    {
        $purchase = $this->makePurchase('Z12345');
        $this->purchaseRepo->method('find')->willReturn($purchase);

        $this->provider->expects($this->never())->method('getByPurchase');
        $this->em->expects($this->never())->method('flush');

        $this->bus->expects($this->once())->method('dispatch')
            ->willReturnCallback(function (UpdateDeliveryStatusMessage $msg, array $stamps) {
                $this->assertSame(42, $msg->purchaseId);
                $this->assertInstanceOf(DelayStamp::class, $stamps[0]);
                return new Envelope($msg);
            });

        ($this->handler)(new CreateParcelMessage(42));
    }

    public function testNoParcelService_throwsUnrecoverable(): void
    {
        $purchase = $this->makePurchase();
        $this->purchaseRepo->method('find')->willReturn($purchase);
        $this->provider->method('getByPurchase')->willThrowException(new ParcelServiceNotFoundException());

        $this->expectException(UnrecoverableMessageHandlingException::class);
        ($this->handler)(new CreateParcelMessage(42));
    }

    public function testApiFailure_throwsRecoverable(): void
    {
        $purchase = $this->makePurchase();
        $this->purchaseRepo->method('find')->willReturn($purchase);
        $service = $this->createMock(ParcelServiceInterface::class);
        $service->method('createParcel')->willThrowException(new \RuntimeException('connection refused'));
        $this->provider->method('getByPurchase')->willReturn($service);

        $this->expectException(RecoverableMessageHandlingException::class);
        ($this->handler)(new CreateParcelMessage(42));
    }

    public function testSuccess_setsTransportNumberFlushesAndSchedules(): void
    {
        $purchase = $this->makePurchase();
        $this->purchaseRepo->method('find')->willReturn($purchase);

        $service = $this->createMock(ParcelServiceInterface::class);
        $service->method('createParcel')->willReturn('Z99999');
        $this->provider->method('getByPurchase')->willReturn($service);

        $purchase->expects($this->once())->method('setTransportNumber')->with('Z99999');
        $this->transportationEventRepo->method('findLatestByPurchase')->willReturn(null);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->bus->expects($this->once())->method('dispatch')
            ->willReturnCallback(function (UpdateDeliveryStatusMessage $msg, array $stamps) {
                $this->assertSame(42, $msg->purchaseId);
                $this->assertInstanceOf(DelayStamp::class, $stamps[0]);
                return new Envelope($msg);
            });

        ($this->handler)(new CreateParcelMessage(42));
    }
}
