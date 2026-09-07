<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;

#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: Voucher::class)]
readonly class VoucherEventListener
{
    public function __construct(
        private CurrencyRepository $currencyRepository,
    ) {}

    public function postLoad(Voucher $voucher): void
    {
        $amount = $voucher->getAmount();

        if ($amount === null) {
            $voucher->setAmountMoney(null);
            return;
        }

        $defaultCurrency = $this->currencyRepository->findOneBy(['isDefault' => true]);
        $voucher->setAmountMoney(
            $defaultCurrency === null ? null : Money::fromCurrency((float)$amount, $defaultCurrency),
        );
    }
}
