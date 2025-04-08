<?php

namespace Greendot\EshopBundle\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Symfony\Component\Workflow\Registry;
use Doctrine\ORM\EntityManagerInterface;

class ManageVoucher
{
    private const GIFT_VOUCHER = 'giftVoucher';

    public function __construct(
        private readonly Registry               $workflowRegistry,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function generateHash(string $voucherCode): string
    {
        return hash('sha256', $voucherCode);
    }

    public function initiateVouchers(Purchase $purchase): Collection
    {
        $vouchers = new ArrayCollection();

        /* @var PurchaseProductVariant $productVariant */
        foreach ($purchase->getProductVariants() as $productVariant) {
            if ($productVariant->getProductVariant()->getProduct()->getProductType()->getName() === 'Dárkový certifikát') {
                $voucher = $this->initiateVoucher($productVariant, $purchase);
                $vouchers->add($voucher);
            }
        }
        return $vouchers;
    }

    private function initiateVoucher(ProductVariant $productVariant, Purchase $purchase): Voucher
    {
        $voucher = new Voucher();
        $voucher->setHash($this->generateHash(uniqid()));

        foreach ($productVariant->getParameters() as $parameter) {
            if ($parameter->getParameterGroup()->getName() === 'certificateValue') {
                $voucher->setAmount($parameter->getData());
                break;
            }
        }

        $voucher->setPurchaseIssued($purchase);
        $voucher->setType(self::GIFT_VOUCHER);

        $this->entityManager->persist($voucher);
        $this->entityManager->flush();

        return $voucher;
    }

    public function handleUsedVouchers(Purchase $purchase, string $state): void
    {
        $vouchers = $purchase->getVouchersUsed();
        foreach ($vouchers as $voucher) {
            $workflow = $this->workflowRegistry->get($voucher);
            if ($workflow->can($voucher, $state)) {
                $workflow->apply($voucher, $state);
            }
        }
    }

    public function handleIssuedVouchers(Purchase $purchase, string $state): void
    {
        $vouchers = $purchase->getVouchersIssued();
        foreach ($vouchers as $voucher) {
            $workflow = $this->workflowRegistry->get($voucher);
            if ($workflow->can($voucher, $state)) {
                $workflow->apply($voucher, $state);
            }
        }
    }
}