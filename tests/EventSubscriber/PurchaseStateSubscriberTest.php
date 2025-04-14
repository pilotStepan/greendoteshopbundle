<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Consent;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\EventSubscriber\PurchaseStateSubscriber;
use Greendot\EshopBundle\Repository\Project\ConsentRepository;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\ManageVoucher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;


/**
 * Test class for PurchaseStateSubscriber.
 *
 * These tests validate that the guard method in the subscriber correctly
 * stops (blocks) the transition when specific conditions are not met.
 */
class PurchaseStateSubscriberTest extends TestCase
{
    private MockObject $manageMails;
    private MockObject $entityManager;
    private MockObject $manageVoucher;
    private MockObject $managePurchase;
    private MockObject $manageClientDiscount;
    private PurchaseStateSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->manageMails = $this->createMock(ManageMails::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->manageVoucher = $this->createMock(ManageVoucher::class);
        $this->managePurchase = $this->createMock(ManagePurchase::class);
        $this->manageClientDiscount = $this->createMock(ManageClientDiscount::class);

        $this->subscriber = new PurchaseStateSubscriber(
            $this->manageMails,
            $this->entityManager,
            $this->manageVoucher,
            $this->managePurchase,
            $this->manageClientDiscount
        );
    }

    public function testGuardReceiveBlocksWhenNoProductVariants(): void
    {
        // Create a Purchase mock with an empty product variants collection.
        $purchase = $this->createPurchaseMock(productVariants: []);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Nelze vytvořit prázdnou objednávku');
    }

    public function testGuardReceiveBlocksWhenNoClient(): void
    {
        // Create a Purchase mock that has some product variant but no client.
        $purchase = $this->createPurchaseMock(client: null);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Objednávka musí mít přiřazeného klienta');
    }

    public function testGuardReceiveBlocksWhenNoPaymentType(): void
    {
        // Create a Purchase mock with valid client but no payment type.
        $purchase = $this->createPurchaseMock(client: new Client(), paymentType: null);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Nebyl vybrán typ platby');
    }

    public function testGuardReceiveBlocksWhenNoTransportation(): void
    {
        // Create a valid payment type so that payment type check passes.
        $paymentType = $this->createValidPaymentType();
        // null transportation is passed to simulate the missing transportation case.
        $purchase = $this->createPurchaseMock(paymentType: $paymentType, transportation: null);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Nebyla vybrána doprava');
    }

    public function testGuardReceiveBlocksWhenIncompatiblePaymentAndTransport(): void
    {
        // Create a payment type mock that returns a collection without the required transportation.
        $paymentType = $this->createMock(PaymentType::class);
        $transportation = $this->createMock(Transportation::class);

        // Ensure that the allowed transportations do not include the given transportation.
        $paymentType->method('getTransportations')
            ->willReturn(new ArrayCollection([$this->createMock(Transportation::class)]));

        // Create a valid client and pass the mismatching payment type and transportation.
        $purchase = $this->createPurchaseMock(client: new Client(), paymentType: $paymentType, transportation: $transportation);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Nekompatibilní typ platby a dopravy');
    }

    public function testGuardReceiveBlocksWhenMissingConsent(): void
    {
        // Set up valid transportation and payment type so these checks pass.
        $transportation = $this->createValidTransportation();
        $paymentType = $this->createValidPaymentType($transportation);

        // Create a Consent object representing the missing required consent.
        $missingConsent = (new Consent())->setDescription('GDPR Consent');

        // Mock the consent repository to return the missing consent.
        $consentRepo = $this->createMock(ConsentRepository::class);
        $consentRepo->method('findMissingRequiredConsent')->willReturn($missingConsent);

        // Configure the entity manager to return this consent repository.
        $this->entityManager->method('getRepository')->with(Consent::class)->willReturn($consentRepo);

        // Create a Purchase mock with valid client, payment type, transportation, but an empty consent collection.
        $purchase = $this->createPurchaseMock(client: new Client(), paymentType: $paymentType, transportation: $transportation, consents: []);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Povinný souhlas nebyl zaškrtnut: GDPR Consent');
    }

    public function testGuardReceiveBlocksWhenInvalidVoucher(): void
    {
        // Ensure valid transportation and payment type so earlier checks pass.
        $transportation = $this->createValidTransportation();
        $paymentType = $this->createValidPaymentType($transportation);

        // Prepare an invalid voucher to simulate the error.
        $invalidVoucher = (new Voucher())->setHash('INVALID123');
        $this->manageVoucher->method('validateUsedVouchers')->willReturn($invalidVoucher);

        $purchase = $this->createPurchaseMock(paymentType: $paymentType, transportation: $transportation);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Nelze uplatnit neplatný voucher: INVALID123');
    }

    public function testGuardReceiveBlocksWhenInvalidClientDiscount(): void
    {
        // Set up valid transportation and payment type.
        $transportation = $this->createValidTransportation();
        $paymentType = $this->createValidPaymentType($transportation);

        // Create a discount and simulate that it is not available.
        $discount = new ClientDiscount();
        $this->manageClientDiscount->method('isAvailable')->willReturn(false);

        $purchase = $this->createPurchaseMock(paymentType: $paymentType, transportation: $transportation, clientDiscount: $discount);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Objednávka má neplatnou klientskou slevu');
    }

    public function testGuardReceiveAllowsTransitionWhenAllValid(): void
    {
        // Build a valid payment type that allows the chosen transportation.
        $paymentType = $this->createMock(PaymentType::class);
        $transportation = $this->createMock(Transportation::class);
        $paymentType->method('getTransportations')->willReturn(new ArrayCollection([$transportation]));

        // Simulate a consent repository that indicates no missing consents.
        $consentRepo = $this->createMock(ConsentRepository::class);
        $consentRepo->method('findMissingRequiredConsent')->willReturn(null);
        $this->entityManager->method('getRepository')->with(Consent::class)->willReturn($consentRepo);

        // Ensure that voucher validation passes and client discount is available.
        $this->manageVoucher->method('validateUsedVouchers')->willReturn(null);
        $this->manageClientDiscount->method('isAvailable')->willReturn(true);

        // Create a Purchase mock with valid client, payment type, transportation, consents, and discount.
        $purchase = $this->createPurchaseMock(
            client: new Client(),
            paymentType: $paymentType,
            transportation: $transportation,
            consents: [new Consent()],
            clientDiscount: new ClientDiscount()
        );

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertFalse($event->isBlocked(), 'Transition should not be blocked');
    }

    private function createPurchaseMock(
        array           $productVariants = [new \stdClass()],
        ?Client         $client = new Client(),
        ?MockObject     $paymentType = null,
        ?MockObject     $transportation = null,
        array           $consents = [new Consent()],
        ?ClientDiscount $clientDiscount = null
    ): MockObject
    {
        if ($paymentType && $transportation) {
            $paymentType->method('getTransportations')->willReturn(new ArrayCollection([$transportation]));
        }

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getProductVariants')->willReturn(new ArrayCollection($productVariants));
        $purchase->method('getClient')->willReturn($client);
        $purchase->method('getPaymentType')->willReturn($paymentType);
        $purchase->method('getTransportation')->willReturn($transportation);
        $purchase->method('getConsents')->willReturn(new ArrayCollection($consents));
        $purchase->method('getClientDiscount')->willReturn($clientDiscount);
        return $purchase;
    }

    private function createValidPaymentType(MockObject $transportation = null): MockObject
    {
        $paymentType = $this->createMock(PaymentType::class);
        $paymentType->method('getTransportations')
            ->willReturn(new ArrayCollection([$transportation]) ?? new ArrayCollection());
        return $paymentType;
    }

    private function createValidTransportation(): MockObject
    {
        return $this->createMock(Transportation::class);
    }

    private function createGuardEvent(object $subject): GuardEvent
    {
        return new GuardEvent(
            $subject,
            $this->createMock(Marking::class),
            $this->createMock(Transition::class),
            $this->createMock(Workflow::class)
        );
    }

    private function assertBlockedWithMessage(GuardEvent $event, string $expectedMessage): void
    {
        $this->assertTrue($event->isBlocked(), 'Transition should be blocked');
        $blockers = iterator_to_array($event->getTransitionBlockerList());
        $this->assertStringContainsString($expectedMessage, $blockers[0]->getMessage());
    }
}