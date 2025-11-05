<?php

namespace Greendot\EshopBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\ParameterGroup;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductType;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Enum\ProductTypeEnum;
use Greendot\EshopBundle\Service\ManageVoucher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;

class ManageVoucherTest extends TestCase
{
    private Registry&MockObject $workflowRegistry;
    private EntityManagerInterface&MockObject $entityManager;
    private ManageVoucher $manageVoucher;

    protected function setUp(): void
    {
        $this->workflowRegistry = $this->createMock(Registry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->manageVoucher = new ManageVoucher(
            $this->workflowRegistry,
            $this->entityManager
        );
    }

    public function testInitiateVouchersCreatesVouchersForGiftCertificates(): void
    {

        
        // Set up a product variant that is a gift certificate
        $productType = new ProductType();
        $productType->setId(ProductTypeEnum::Voucher->value);
        $product = new Product();
        $product->setProductType($productType);
        $variant = new ProductVariant();
        $variant->setProduct($product);

        // Add a certificate value parameter to the variant
        $paramGroup = new ParameterGroup();
        $paramGroup->setName('certificateValue');
        $parameter = new Parameter();
        $parameter->setParameterGroup($paramGroup);
        $parameter->setData(100);
        $variant->addParameter($parameter);

        // Create a purchase that includes the above product variant
        $purchaseProductVariant = new PurchaseProductVariant();
        $purchaseProductVariant->setProductVariant($variant);
        $purchase = new Purchase();
        $purchase->addProductVariant($purchaseProductVariant);

        // Expect persistence and flush calls on the entity manager
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Voucher::class));
        $this->entityManager->expects($this->once())
            ->method('flush');

        $vouchers = $this->manageVoucher->initiateVouchers($purchase);

        $this->assertCount(1, $vouchers);
        $voucher = $vouchers->first();
        $this->assertSame(100, $voucher->getAmount());
        $this->assertSame(ManageVoucher::GIFT_VOUCHER, $voucher->getType());
        $this->assertSame($purchase, $voucher->getPurchaseIssued());
    }

    public function testInitiateVouchersSkipsNonGiftProducts(): void
    {
        // Set up a product variant that is not a gift certificate
        $productType = new ProductType();
        $productType->setId(ProductTypeEnum::Standard->value);
        $product = new Product();
        $product->setProductType($productType);
        $variant = new ProductVariant();
        $variant->setProduct($product);

        $purchaseProductVariant = new PurchaseProductVariant();
        $purchaseProductVariant->setProductVariant($variant);
        $purchase = new Purchase();
        $purchase->addProductVariant($purchaseProductVariant);

        // Should return an empty voucher collection
        $vouchers = $this->manageVoucher->initiateVouchers($purchase);
        $this->assertEmpty($vouchers);
    }

    public function testValidateIssuedVouchersReturnsFirstInvalid(): void
    {
        // Create two issued vouchers and add them to a purchase
        $voucher1 = new Voucher();
        $voucher2 = new Voucher();
        $purchase = new Purchase();
        $purchase->addVoucherIssued($voucher1);
        $purchase->addVoucherIssued($voucher2);

        // Mock workflow to mark voucher1 as invalid
        $workflow = $this->createMock(Workflow::class);
        $workflow->method('can')->willReturnCallback(
            fn($voucher, $transition) => $voucher !== $voucher1
        );
        $this->workflowRegistry->method('get')
            ->willReturn($workflow);

        $result = $this->manageVoucher->validateIssuedVouchers($purchase, 'transition');
        $this->assertSame($voucher1, $result);
    }

    public function testHandleIssuedVouchersAppliesValidTransitions(): void
    {
        // Create two issued vouchers
        $voucher1 = new Voucher();
        $voucher2 = new Voucher();
        $purchase = new Purchase();
        $purchase->addVoucherIssued($voucher1);
        $purchase->addVoucherIssued($voucher2);

        // Only voucher1 is valid for the transition
        $workflow = $this->createMock(Workflow::class);
        $workflow->method('can')->willReturnCallback(
            fn($voucher, $transition) => $voucher === $voucher1
        );

        $workflow->expects($this->once())
            ->method('apply')
            ->with($voucher1, 'transition');
        $this->workflowRegistry->method('get')
            ->willReturn($workflow);

        $this->manageVoucher->handleIssuedVouchers($purchase, 'transition');
    }

    public function testValidateUsedVouchersReturnsFirstInvalid(): void
    {
        // Create two used vouchers
        $voucher1 = new Voucher();
        $voucher2 = new Voucher();
        $purchase = new Purchase();
        $purchase->addVoucherUsed($voucher1);
        $purchase->addVoucherUsed($voucher2);

        // Mark voucher2 as invalid
        $workflow = $this->createMock(Workflow::class);
        $workflow->method('can')->willReturnCallback(
            fn($voucher, $transition) => $voucher !== $voucher2
        );
        $this->workflowRegistry->method('get')
            ->willReturn($workflow);

        $result = $this->manageVoucher->validateUsedVouchers($purchase, 'transition');
        $this->assertSame($voucher2, $result);
    }

    public function testHandleUsedVouchersAppliesValidTransitions(): void
    {
        // Create two used vouchers
        $voucher1 = new Voucher();
        $voucher2 = new Voucher();
        $purchase = new Purchase();
        $purchase->addVoucherUsed($voucher1);
        $purchase->addVoucherUsed($voucher2);

        // Only voucher2 is valid for the transition
        $workflow = $this->createMock(Workflow::class);
        $workflow->method('can')->willReturnCallback(
            fn($voucher, $transition) => $voucher === $voucher2
        );
        $workflow->expects($this->once())
            ->method('apply')
            ->with($voucher2, 'transition');

        $this->workflowRegistry->method('get')
            ->willReturn($workflow);

        $this->manageVoucher->handleUsedVouchers($purchase, 'transition');
    }

    public function testInitiateVoucherWithoutCertificateValueParameter(): void
    {
        // Set up a gift certificate without the certificate value parameter
        $productType = new ProductType();
        $productType->setId(ProductTypeEnum::Voucher->value);
        $product = new Product();
        $product->setProductType($productType);
        $variant = new ProductVariant();
        $variant->setProduct($product);

        $purchaseProductVariant = new PurchaseProductVariant();
        $purchaseProductVariant->setProductVariant($variant);
        $purchase = new Purchase();
        $purchase->addProductVariant($purchaseProductVariant);

        // Expect persistence call; no certificate value parameter, so amount stays null
        $this->entityManager->expects($this->once())->method('persist');
        $vouchers = $this->manageVoucher->initiateVouchers($purchase);

        $this->assertCount(1, $vouchers);
        $this->assertNull($vouchers->first()->getAmount());
    }
}