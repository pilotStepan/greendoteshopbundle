<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Notification\Handler;

use Greendot\EshopBundle\Dto\SmsMessageDto;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Notification\Handler\CustomerSmsHandler;
use Greendot\EshopBundle\Notification\Sms\OrderTransitionSmsFactoryInterface;
use Neogate\SmsConnect\SmsConnect;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CustomerSmsHandlerTest extends TestCase
{
    /**
     * The factory logs an error for a missing phone; previewing must not reach it.
     */
    public function testBuildSmsWithoutPhoneSkipsTheFactory(): void
    {
        $factory = $this->createMock(OrderTransitionSmsFactoryInterface::class);
        $factory->expects($this->never())->method('create');

        $purchase = (new Purchase())->setClient(new Client());

        $this->assertNull($this->handler($factory)->buildSms($purchase, 'log_send'));
    }

    public function testBuildSmsUsesTheFactory(): void
    {
        $sms = new SmsMessageDto('420777123456', 'Objednávka odeslána.');
        $factory = $this->createMock(OrderTransitionSmsFactoryInterface::class);
        $factory->expects($this->once())->method('create')->with($this->anything(), 'log_send')->willReturn($sms);

        $purchase = (new Purchase())->setClient((new Client())->setPhone('777 123 456'));

        $this->assertSame($sms, $this->handler($factory)->buildSms($purchase, 'log_send'));
    }

    private function handler(OrderTransitionSmsFactoryInterface $factory): CustomerSmsHandler
    {
        return new CustomerSmsHandler($this->createMock(SmsConnect::class), $factory, new NullLogger());
    }
}
