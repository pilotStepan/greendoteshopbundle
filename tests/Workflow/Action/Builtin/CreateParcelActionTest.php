<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Workflow\Action\Builtin;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Parcel\Exception\ParcelServiceNotFoundException;
use Greendot\EshopBundle\Parcel\ParcelServiceInterface;
use Greendot\EshopBundle\Parcel\ParcelServiceProviderInterface;
use Greendot\EshopBundle\Parcel\TransportationAPI;
use Greendot\EshopBundle\Tests\Stub\PurchaseFlowFixtures;
use Greendot\EshopBundle\Workflow\Action\Builtin\CreateParcelAction;
use PHPUnit\Framework\TestCase;

class CreateParcelActionTest extends TestCase
{
    use PurchaseFlowFixtures;

    private function describe(Purchase $purchase, ?ParcelServiceInterface $service): \Greendot\EshopBundle\Workflow\Action\ActionDescription
    {
        $provider = $this->createMock(ParcelServiceProviderInterface::class);
        if ($service === null) {
            $provider->method('getByPurchase')->willThrowException(new ParcelServiceNotFoundException('none'));
        } else {
            $provider->method('getByPurchase')->willReturn($service);
        }

        return (new CreateParcelAction($this->buildManagePurchase(), $provider))->describe($purchase, 'log_prepare_to_ship');
    }

    private function purchase(?TransportationAPI $api, ?string $transportNumber = null): Purchase
    {
        $purchase = new Purchase();
        $purchase->setTransportation((new Transportation())->setName('Doprava X')->setTransportationAPI($api));
        $purchase->setTransportNumber($transportNumber);

        return $purchase;
    }

    private function service(bool $polling = true): ParcelServiceInterface
    {
        $service = $this->createMock(ParcelServiceInterface::class);
        $service->method('supportsStatusPolling')->willReturn($polling);

        return $service;
    }

    public function testNewParcelNamesTheCarrier(): void
    {
        $d = $this->describe($this->purchase(TransportationAPI::PACKETA), $this->service());

        $this->assertSame('Založit zásilku', $d->label);
        $this->assertSame('Zásilkovna', $d->detail);
    }

    public function testTransportationWithoutIntegrationWarnsInsteadOfHiding(): void
    {
        $d = $this->describe($this->purchase(null), null);

        $this->assertSame('Zásilku založit ručně', $d->label);
    }

    public function testExistingParcelIsOnlyTracked(): void
    {
        $d = $this->describe($this->purchase(TransportationAPI::DPD, '123'), $this->service());

        $this->assertSame(['Zásilka už existuje', 'DPD 123, stav se bude sledovat'], [$d->label, $d->detail]);
    }

    public function testManualParcelWithoutPollingIsNotTracked(): void
    {
        $d = $this->describe($this->purchase(TransportationAPI::DPD, '123'), $this->service(polling: false));

        $this->assertSame(['Zásilka už existuje', 'DPD 123, zadaná ručně, stav se nesleduje'], [$d->label, $d->detail]);
    }
}
