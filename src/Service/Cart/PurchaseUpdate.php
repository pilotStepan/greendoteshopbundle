<?php

namespace Greendot\EshopBundle\Service\Cart;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Dto\PurchaseCheckoutInput;
use Greendot\EshopBundle\Entity\Project\Consent;
use Greendot\EshopBundle\Entity\Project\Purchase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PurchaseUpdate
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ){}

    public function patch(PurchaseCheckoutInput $purchaseCheckoutInput): void
    {

    }

    public function post(PurchaseCheckoutInput $purchaseCheckoutInput): void
    {
        $this->update($purchaseCheckoutInput, ['']);
    }

    private function update(PurchaseCheckoutInput $purchaseCheckoutInput, array $groups): void
    {

    }

    private function mapSessionToDTO(): PurchaseCheckoutInput
    {
        $purchase = $this->em->getRepository(PurchaseCheckoutInput::class)->findOneBySession();
        if (!$purchase){
            $this->logger->warning('Checkout failed: purchase not found for session');
            throw new InvalidArgumentException('Košík nenalezen');
        }
        assert($purchase instanceof Purchase);
        $dto = new PurchaseCheckoutInput();
        $dto->consents = $this->em->getRepository(Consent::class)->getIdsForPurchase($purchase);
        foreach ($purchase->getPurchaseDiscussions() as $discussion){
            $dto->notes[] = $discussion->getContent();
        }


    }


}