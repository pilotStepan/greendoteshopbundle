<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Workflow\Registry;
use Doctrine\ORM\EntityManagerInterface;

class ManageVoucher
{
    private const GIFT_VOUCHER = 'giftVoucher';

    public function __construct(
        private readonly Registry               $workflowRegistry,
        private readonly EntityManagerInterface $entityManager
    ){}

    public function generateHash(string $voucherCode): string
    {
        return hash('sha256', $voucherCode);
    }

    public function validateVoucher(Voucher $voucher, string $couponType): bool
    {
        if (!$this->isGiftVoucher($voucher, $couponType)) {
            return false;
        }

        return $this->canVoucherBeUsed($voucher);
    }


    private function canVoucherBeUsed(Voucher $voucher): bool
    {
        $workflow = $this->workflowRegistry->get($voucher);
        return $workflow->can($voucher, 'use');
    }

    public function initiateVoucher(ProductVariant $productVariant, Purchase $purchase): Voucher
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

    public function enableVoucher(Voucher $voucher): void
    {

    }

    public function disableVoucher(Voucher $voucher): void
    {

    }

    public function useVoucher(Voucher $voucher): void
    {

    }

    public function handleVouchers(Purchase $purchase, string $state): void
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