<?php

namespace Greendot\EshopBundle\MessageHandler\Notification;

use RuntimeException;
use Greendot\EshopBundle\Service\ManageMails;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Repository\Project\VoucherRepository;
use Greendot\EshopBundle\Message\Notification\IssuedVoucherEmail;

#[AsMessageHandler]
readonly class IssuedVoucherEmailHandler
{
    public function __construct(
        private VoucherRepository $voucherRepository,
        private ManageMails       $manageMails,
    ) {}

    public function __invoke(IssuedVoucherEmail $msg): void
    {
        $voucherId = $msg->voucherId;
        $voucher = $this->voucherRepository->find($voucherId);

        if (!$voucher) {
            throw new RuntimeException('Voucher not found for ID: ' . $voucherId);
        }

        $this->manageMails->sendIssuedVoucherEmail($voucher);
    }
}