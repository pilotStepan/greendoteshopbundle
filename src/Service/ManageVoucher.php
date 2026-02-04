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
use Greendot\EshopBundle\Repository\Project\VoucherRepository;

class ManageVoucher
{
    public const GIFT_VOUCHER = 'giftVoucher';

    public function __construct(
        private readonly Registry               $workflowRegistry,
        private readonly EntityManagerInterface $em,
        private readonly VoucherRepository      $voucherRepository,
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
        $voucher = (new Voucher())
            ->setHash($this->generateHash())
            ->setAmount($this->findCertificateValue($productVariant))
            ->setPurchaseIssued($purchase)
            ->setType(self::GIFT_VOUCHER)
        ;

        $this->em->persist($voucher);
        $this->em->flush();

        return $voucher;
    }

    private function generateHash(): string
    {
        do {
            $hash = substr(bin2hex(random_bytes(3)), 0, 6);
        } while ($this->voucherRepository->findOneBy(['hash' => $hash]) !== null);

        return $hash;
    }

    private function findCertificateValue(ProductVariant $productVariant): ?float
    {
        foreach ($productVariant->getParameters() as $parameter) {
            if ($parameter->getParameterGroup()->getName() === 'certificateValue') {
                return $parameter->getData();
            }
        }
        return null;
    }
}
