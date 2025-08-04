<?php

namespace Greendot\EshopBundle\Tests\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Note;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\TransportationAction;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\MessageRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\InvoiceMaker;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\Parcel\ParcelServiceInterface;
use Greendot\EshopBundle\Service\Parcel\ParcelServiceProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Workflow\Registry;

class ManagePurchaseTest extends TestCase
{
    private PurchaseRepository&MockObject $purchaseRepository;
    private ParcelServiceProvider&MockObject $parcelServiceProvider;
    private ManagePurchase $managePurchase;

    protected function setUp(): void
    {
        $workflowRegistry = $this->createMock(Registry::class);
        $this->purchaseRepository = $this->createMock(PurchaseRepository::class);
        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $messageRepository = $this->createMock(MessageRepository::class);
        $logger = $this->createMock(LoggerInterface::class);
        $invoiceMaker = $this->createMock(InvoiceMaker::class);
        $this->parcelServiceProvider = $this->createMock(ParcelServiceProvider::class);
        $requestStack = $this->createMock(RequestStack::class);

        $session = $this->createMock(Session::class);
        $session->method('isStarted')->willReturn(true);
        $session->method('get')->willReturn($this->createMock(Currency::class));
        $requestStack->method('getSession')->willReturn($session);

        $this->managePurchase = new ManagePurchase(
            $workflowRegistry,
            $this->purchaseRepository,
            $currencyRepository,
            $messageRepository,
            $logger,
            $invoiceMaker,
            $this->parcelServiceProvider,
            $requestStack
        );
    }

    public function testAddProductVariantToPurchaseNewItem(): void
    {
        $purchase = $this->createMock(Purchase::class);
        $productVariant = $this->createMock(ProductVariant::class);
        $productVariant->method('getId')->willReturn(123);

        $purchase->method('getProductVariants')
            ->willReturn(new ArrayCollection());

        $purchase->expects($this->once())
            ->method('addProductVariant')
            ->with($this->callback(function ($ppv) use ($productVariant) {
                return $ppv->getProductVariant() === $productVariant
                    && $ppv->getAmount() === 2;
            }));

        $result = $this->managePurchase->addProductVariantToPurchase($purchase, $productVariant, 2);
        $this->assertSame($purchase, $result);
    }

    public function testAddProductVariantToPurchaseExistingItem(): void
    {
        $productVariant = $this->createMock(ProductVariant::class);
        $productVariant->method('getId')->willReturn(123);

        $existingPPV = $this->createMock(PurchaseProductVariant::class);
        $existingPPV->method('getProductVariant')->willReturn($productVariant);
        // Stub the current amount to 2 so that adding 1 results in 3.
        $existingPPV->method('getAmount')->willReturn(2);
        $existingPPV->expects($this->once())
            ->method('setAmount')
            ->with(3);

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getProductVariants')
            ->willReturn(new ArrayCollection([$existingPPV]));

        $result = $this->managePurchase->addProductVariantToPurchase($purchase, $productVariant, 1);
        $this->assertSame($purchase, $result);
    }


    public function testCalculateInquiryNumberWithPurchase(): void
    {
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getDateIssue')->willReturn(new \DateTime('2023-01-01'));
        $purchase->method('getId')->willReturn(456);

        $result = $this->managePurchase->generateInquiryNumber($purchase);
        $this->assertSame('1672531200456', $result);
    }

    public function testGenerateTransportDataSuccessful(): void
    {
        // Set up transportation and its action.
        $transportation = $this->createMock(Transportation::class);
        $action = $this->createMock(TransportationAction::class);
        $action->method('getId')->willReturn(3); // Non-pickup action.
        $transportation->method('getTransportationAction')->willReturn($action);
        $transportation->method('getId')->willReturn(1);

        // Set up purchase to return the transportation.
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getTransportation')->willReturn($transportation);

        // Create a mock for ParcelServiceInterface.
        $parcelService = $this->createMock(ParcelServiceInterface::class);
        $parcelService->method('createParcel')->with($purchase)->willReturn('TRACK123');

        // When parcel service provider is asked, return our mock.
        $this->parcelServiceProvider->method('get')->with(1)->willReturn($parcelService);

        // Expect that the purchase gets its transport number set.
        $purchase->expects($this->once())
            ->method('setTransportNumber')
            ->with('TRACK123');

        $this->managePurchase->generateTransportData($purchase);
    }

    public function testFindPurchaseByInquiryNumberThrowsExceptionForShortInquiryNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Inquiry ID has a wrong format.");
        // Inquiry number with exactly 10 characters (timestamp only) is invalid.
        $inquiryNumber = "0123456789";
        $this->managePurchase->findPurchaseByInquiryNumber($inquiryNumber);
    }

    public function testFindPurchaseByInquiryNumberThrowsExceptionWhenPurchaseNotFound(): void
    {
        $inquiryNumber = "0123456789123"; // 10-digit timestamp + "123"
        // Expect the repository to look for purchase ID "123" and return null.
        $this->purchaseRepository->expects($this->once())
            ->method('find')
            ->with("123")
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Purchase not found for inquiry number: $inquiryNumber.");
        $this->managePurchase->findPurchaseByInquiryNumber($inquiryNumber);
    }

    public function testFindPurchaseByInquiryNumberReturnsPurchase(): void
    {
        $inquiryNumber = "0123456789123"; // 10-digit timestamp + "123"
        $dummyPurchase = new Purchase();
        // Expect the repository to return the dummy purchase when searching by "123".
        $this->purchaseRepository->expects($this->once())
            ->method('find')
            ->with("123")
            ->willReturn($dummyPurchase);

        $result = $this->managePurchase->findPurchaseByInquiryNumber($inquiryNumber);
        $this->assertSame($dummyPurchase, $result);
    }
}