<?php

namespace Greendot\EshopBundle\Tests\Parcel\MessageHandler;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Parcel\ParcelServiceInterface;
use Greendot\EshopBundle\Parcel\ParcelServiceProviderInterface;
use Greendot\EshopBundle\Parcel\TransportationAPI;
use Greendot\EshopBundle\Parcel\Exception\ParcelServiceNotFoundException;
use Greendot\EshopBundle\Parcel\Message\CreateParcelMessage;
use Greendot\EshopBundle\Parcel\Message\UpdateDeliveryStatusMessage;
use Greendot\EshopBundle\Parcel\MessageHandler\CreateParcelHandler;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Repository\Project\TransportationEventRepository;
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

    private function makePurchase(?string $transportNumber = null, bool $isEndState = false): Purchase
    {
        $transportation = $this->createMock(Transportation::class);
        $transportation->method('getTransportationAPI')->willReturn(TransportationAPI::PACKETA);

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getId')->willReturn(42);
        $purchase->method('getTransportNumber')->willReturn($transportNumber);
        $purchase->method('hasAnyPlace')->willReturn($isEndState);
        $purchase->method('getTransportation')->willReturn($transportation);
        return $purchase;
    }

    public function testPurchaseNotFound_throwsUnrecoverable(): void
    {
        $this->purchaseRepo->method('find')->willReturn(null);
        $this->expectException(UnrecoverableMessageHandlingException::class);
        ($this->handler)(new CreateParcelMessage(42));
    }

    public function testEndStatePurchase_skipsCreateAndDoesNotSchedule(): void
    {
        $purchase = $this->makePurchase(isEndState: true);
        $this->purchaseRepo->method('find')->willReturn($purchase);

        $this->provider->expects($this->never())->method('getByPurchase');
        $this->bus->expects($this->never())->method('dispatch');

        ($this->handler)(new CreateParcelMessage(42));
    }

    public function testAlreadyHasTransportNumber_skipsCreateAndSchedulesStatusCheck(): void
    {
        $purchase = $this->makePurchase('Z12345');
        $this->purchaseRepo->method('find')->willReturn($purchase);

        $service = $this->createMock(ParcelServiceInterface::class);
        $service->method('supportsStatusPolling')->willReturn(true);
        $this->provider->expects($this->once())->method('getByPurchase')->willReturn($service);
        $this->em->expects($this->never())->method('flush');

        $this->bus->expects($this->once())->method('dispatch')
            ->willReturnCallback(function (UpdateDeliveryStatusMessage $msg, array $stamps) {
                $this->assertSame(42, $msg->purchaseId);
                $this->assertInstanceOf(DelayStamp::class, $stamps[0]);
                return new Envelope($msg);
            });

        ($this->handler)(new CreateParcelMessage(42));
    }

    // A carrier ID entered manually via CMS (e.g. DPD's transport_number) does not necessarily
    // give the carrier's status API what it needs (e.g. DPD's numeric shipmentId) - polling
    // must not be scheduled in that case, and should say so once instead of retrying forever.
    public function testAlreadyHasTransportNumberButNoStatusId_skipsCreateAndDoesNotSchedule(): void
    {
        $purchase = $this->makePurchase('DR0639136093M');
        $this->purchaseRepo->method('find')->willReturn($purchase);

        $service = $this->createMock(ParcelServiceInterface::class);
        $service->method('supportsStatusPolling')->willReturn(false);
        $this->provider->method('getByPurchase')->willReturn($service);

        $this->bus->expects($this->never())->method('dispatch');
        $this->em->expects($this->never())->method('flush');

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

    // Transient errors are rethrown as-is (not wrapped) so Symfony's max_retries on the
    // `parcel` transport actually applies instead of retrying forever.
    public function testApiFailure_rethrowsOriginalException(): void
    {
        $purchase = $this->makePurchase();
        $this->purchaseRepo->method('find')->willReturn($purchase);
        $service = $this->createMock(ParcelServiceInterface::class);
        $service->method('createParcel')->willThrowException(new \RuntimeException('connection refused'));
        $this->provider->method('getByPurchase')->willReturn($service);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('connection refused');
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
