<?php

namespace Greendot\EshopBundle\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Workflow\Registry;
use Doctrine\ORM\EntityManagerInterface;

class ManageVoucher
{
    public const GIFT_VOUCHER = 'giftVoucher';

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

        foreach ($purchase->getProductVariants() as $purchaseProductVariant) {
            $productVariant = $purchaseProductVariant->getProductVariant();
            if ($productVariant->getProduct()?->getProductType()?->getName() === 'Dárkový certifikát') {
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

    public function validateIssuedVouchers(Purchase $purchase, string $transitionName): ?Voucher
    {
        foreach ($purchase->getVouchersIssued() as $voucher) {
            $workflow = $this->workflowRegistry->get($voucher);
            if (!$workflow->can($voucher, $transitionName)) {
                return $voucher;
            }
        }
        return null;
    }

    public function handleIssuedVouchers(Purchase $purchase, string $transitionName): void
    {
        foreach ($purchase->getVouchersIssued() as $voucher) {
            $workflow = $this->workflowRegistry->get($voucher);
            if ($workflow->can($voucher, $transitionName)) {
                $workflow->apply($voucher, $transitionName);
            }
        }
    }

    public function validateUsedVouchers(Purchase $purchase, string $transitionName): ?Voucher
    {
        foreach ($purchase->getVouchersUsed() as $voucher) {
            $workflow = $this->workflowRegistry->get($voucher);
            if (!$workflow->can($voucher, $transitionName)) {
                return $voucher;
            }
        }
        return null;
    }

    public function handleUsedVouchers(Purchase $purchase, string $transitionName): void
    {
        foreach ($purchase->getVouchersUsed() as $voucher) {
            $workflow = $this->workflowRegistry->get($voucher);
            if ($workflow->can($voucher, $transitionName)) {
                $workflow->apply($voucher, $transitionName);
            }
        }
    }
}