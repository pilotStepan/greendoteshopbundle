<?php

namespace Greendot\EshopBundle\Tests\Service;

use Doctrine\Common\Collections\ArrayCollection;
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
use Greendot\EshopBundle\Repository\Project\VoucherRepository;
use Greendot\EshopBundle\Service\ManageVoucher;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;

class ManageVoucherTest extends TestCase
{
    private Registry&MockObject $workflowRegistry;
    private EntityManagerInterface&MockObject $entityManager;
    private VoucherRepository&MockObject $voucherRepository;
    private ManageVoucher $manageVoucher;

    protected function setUp(): void
    {
        $this->workflowRegistry = $this->createMock(Registry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->voucherRepository = $this->createMock(VoucherRepository::class);

        $this->entityManager->method('wrapInTransaction')
            ->willReturnCallback(function (callable $func) { return $func(); });

        $this->voucherRepository->method('findOneBy')->willReturn(null);

        $this->manageVoucher = new ManageVoucher(
            $this->workflowRegistry,
            $this->entityManager,
            $this->voucherRepository,
        );
    }

    public function testInitiateVouchersCreatesVouchersForGiftCertificates(): void
    {
        $productType = new ProductType();
        $productType->setId(ProductTypeEnum::Voucher->value);
        $product = new Product();
        $product->setProductType($productType);
        $variant = new ProductVariant();
        $variant->setProduct($product);

        $paramGroup = new ParameterGroup();
        $paramGroup->setName('certificateValue');
        $parameter = new Parameter();
        $parameter->setParameterGroup($paramGroup);
        $parameter->setData(100);
        $variant->addParameter($parameter);

        $purchaseProductVariant = new PurchaseProductVariant();
        $purchaseProductVariant->setProductVariant($variant);
        $purchase = new Purchase();
        $purchase->addProductVariant($purchaseProductVariant);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Voucher::class));

        $vouchers = $this->manageVoucher->initiateVouchers($purchase);

        $this->assertCount(1, $vouchers);
        $voucher = $vouchers->first();
        $this->assertSame(100, $voucher->getAmount());
        $this->assertSame(ManageVoucher::GIFT_VOUCHER, $voucher->getType());
        $this->assertSame($purchase, $voucher->getPurchaseIssued());
    }

    public function testInitiateVouchersSkipsNonGiftProducts(): void
    {
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

        $vouchers = $this->manageVoucher->initiateVouchers($purchase);
        $this->assertEmpty($vouchers);
    }

    public function testInitiateVoucherWithoutCertificateValueParameter(): void
    {
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

        $this->entityManager->expects($this->once())->method('persist');
        $vouchers = $this->manageVoucher->initiateVouchers($purchase);

        $this->assertCount(1, $vouchers);
        $this->assertNull($vouchers->first()->getAmount());
    }

    public function testValidateVouchersTransitionReturnsTrueWhenAllValid(): void
    {
        $voucher1 = new Voucher();
        $voucher2 = new Voucher();

        $workflow = $this->createMock(Workflow::class);
        $workflow->method('can')->willReturn(true);
        $this->workflowRegistry->method('get')->willReturn($workflow);

        $result = $this->manageVoucher->validateVouchersTransition(
            new ArrayCollection([$voucher1, $voucher2]),
            'use'
        );

        $this->assertTrue($result);
    }

    public function testValidateVouchersTransitionThrowsForInvalidVoucher(): void
    {
        $voucher1 = new Voucher();
        $voucher1->setHash('INVALID123');

        $workflow = $this->createMock(Workflow::class);
        $workflow->method('can')->willReturn(false);
        $this->workflowRegistry->method('get')->willReturn($workflow);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('INVALID123');

        $this->manageVoucher->validateVouchersTransition(
            new ArrayCollection([$voucher1]),
            'use'
        );
    }

    public function testHandleVouchersTransitionAppliesValidTransitions(): void
    {
        $voucher1 = new Voucher();
        $voucher2 = new Voucher();

        $workflow = $this->createMock(Workflow::class);
        $workflow->method('can')->willReturnCallback(
            fn($voucher, $transition) => $voucher === $voucher1
        );
        $workflow->expects($this->once())
            ->method('apply')
            ->with($voucher1, 'payment');
        $this->workflowRegistry->method('get')->willReturn($workflow);

        $this->manageVoucher->handleVouchersTransition(
            new ArrayCollection([$voucher1, $voucher2]),
            'payment'
        );
    }

    public function testHandleVouchersTransitionSkipsInvalidVouchers(): void
    {
        $voucher = new Voucher();

        $workflow = $this->createMock(Workflow::class);
        $workflow->method('can')->willReturn(false);
        $workflow->expects($this->never())->method('apply');
        $this->workflowRegistry->method('get')->willReturn($workflow);

        $this->manageVoucher->handleVouchersTransition(
            new ArrayCollection([$voucher]),
            'payment_issue'
        );
    }
}
