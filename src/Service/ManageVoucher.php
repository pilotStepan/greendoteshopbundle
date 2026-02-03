<?php

namespace Greendot\EshopBundle\Service;

use LogicException;
use Symfony\Component\Workflow\Registry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\Collection;
use Greendot\EshopBundle\Enum\ProductTypeEnum;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Doctrine\Common\Collections\ArrayCollection;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\ProductVariant;

class ManageVoucher
{
    public const GIFT_VOUCHER = 'giftVoucher';

    public function __construct(
        private readonly Registry               $workflowRegistry,
        private readonly EntityManagerInterface $em,
    ) {}

    public function initiateVouchers(Purchase $purchase): Collection
    {
        $vouchers = new ArrayCollection();

        foreach ($purchase->getProductVariants() as $purchaseProductVariant) {
            $productVariant = $purchaseProductVariant->getProductVariant();
            if ($productVariant->getProduct()?->getProductType()?->getId() === ProductTypeEnum::Voucher->value) {
                $voucher = $this->initiateVoucher($productVariant, $purchase);
                $vouchers->add($voucher);
            }
        }
        return $vouchers;
    }

    /**
     * @throws LogicException
     */
    public function use(Voucher $voucher, Purchase $purchase): void
    {
        $this->em->wrapInTransaction(function () use ($purchase, $voucher): void {
            $workflow = $this->workflowRegistry->get($voucher);
            $workflow->apply($voucher, 'use');
            $voucher->setPurchaseUsed($purchase);
        });
    }

    /**
     * @param Collection<int, Voucher> $vouchers
     */
    public function handleVouchersTransition(Collection $vouchers, string $transitionName): void
    {
        foreach ($vouchers as $voucher) {
            $workflow = $this->workflowRegistry->get($voucher);
            if ($workflow->can($voucher, $transitionName)) {
                $workflow->apply($voucher, $transitionName);
            }
        }
    }

    /**
     * @param Collection<int, Voucher> $vouchers
     * @throws LogicException
     */
    public function validateVouchersTransition(Collection $vouchers, string $transitionName): bool
    {
        foreach ($vouchers as $voucher) {
            $workflow = $this->workflowRegistry->get($voucher);
            if (!$workflow->can($voucher, $transitionName)) {
                throw new LogicException("Nelze uplatnit neplatný voucher: " . $voucher->getHash());
            }
        }
        return true;
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

        $this->em->persist($voucher);
        $this->em->flush();

        return $voucher;
    }

    private function generateHash(string $voucherCode): string
    {
        return substr(hash('sha256', $voucherCode), 0, 6);
    }
}
