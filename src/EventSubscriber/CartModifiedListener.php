<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Greendot\EshopBundle\DataLayer\Event\ModifyCart;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Explanation:
 *
 * This triggers dataLayer event for cartManipulation e.g "add_to_cart", "remove_from_cart"
 *
 * Because we don't update existing purchaseProductVariants but rather remove them and then add them with modified amount
 * I had to use this wierd thing to handle it.
 *
 * How it works?
 * I check changes on purchaseProductVariants and emit relevant event
 *
 * If we are "modifying" existing purchaseProductVariant it will be in both insertions and update -> So we use the update because we get the diff in quantity which we need
 * If new PurchaseProductVariant is added it will only be in insertions so we just use that
 * if PurchaseProductVariant is completely removed it won't be in insertion but only in update
 *
 * -- getScheduledEntityDeletions for some reason never does anything so there is remove even as default if somehow that changes
 *
 */


#[AsDoctrineListener(event: Events::onFlush)]
class CartModifiedListener
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ){}

    public function onFlush(OnFlushEventArgs $onFlushEventArgs): void
    {
        $em = $onFlushEventArgs->getObjectManager();
        $uow = $em->getUnitOfWork();


        $insertEvents = [];
        $removeEvents = [];
        $updateEvents = [];

        // 1. Handle New Variants (Add to cart)
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof PurchaseProductVariant) {
                $insertEvents[$entity->getProductVariant()->getId()] = new ModifyCart($entity, $entity->getAmount(), ModifyCart::Add);
            }
        }

        // 2. Handle Deleted Variants (Remove from cart)
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof PurchaseProductVariant) {
                $removeEvents[$entity->getProductVariant()->getId()] = new ModifyCart($entity, $entity->getAmount(), ModifyCart::Remove);
            }
        }

        // 3. Handle Updates (Amount changes)
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof PurchaseProductVariant) {
                $forceRemove = true;
                if (isset($insertEvents[$entity->getProductVariant()->getId()])) {
                    unset($insertEvents[$entity->getProductVariant()->getId()]);
                    $forceRemove = false;
                }

                $changeSet = $uow->getEntityChangeSet($entity);
                ['amount' => $amount, 'isOld' => $isOld] = $this->getDifference($entity, $changeSet);

                if ($forceRemove or $amount < 0) {
                    $removeEvents[$entity->getProductVariant()->getId()] = new ModifyCart($entity, abs($amount), ModifyCart::Remove);
                } else {
                    $updateEvents[$entity->getProductVariant()->getId()] = new ModifyCart($entity, $amount, ModifyCart::Add);
                }
            }
        }

        $events = array_merge($insertEvents, $updateEvents, $removeEvents);
        foreach ($events as $event){
            $this->eventDispatcher->dispatch($event);
        }

    }

    /**
     * @param PurchaseProductVariant $purchaseProductVariant
     * @param array $changeSet
     * @return array|null
     */
    #[ArrayShape(['amount' => 'int', 'purchaseProductVariant' => PurchaseProductVariant::class, 'isOld' => 'bool'])]
    private function getDifference(PurchaseProductVariant $purchaseProductVariant, array $changeSet): ?array
    {
        if (count($changeSet) !== 1) return null;
        $changeSet = $changeSet[array_key_first($changeSet)];
        if (!isset($changeSet[0])) return null;
        $changeSet = $changeSet[0];

        $oldPurchaseProductVariant = null;

        switch (get_class($changeSet)) {
            case PurchaseProductVariant::class:
                $oldPurchaseProductVariant = $changeSet;
                break;
            case Purchase::class:
                $oldPurchaseProductVariant = $this->getPurchaseProductVariantFromPurchase($purchaseProductVariant, $changeSet);
                break;
            default:
                return null;
        }
        $amount = $purchaseProductVariant->getAmount();
        if ($oldPurchaseProductVariant) {
            $amount = $oldPurchaseProductVariant->getAmount() - $purchaseProductVariant->getAmount();
        }
        return [
            'amount' => $amount,
            'purchaseProductVariant' => $purchaseProductVariant,
            'isOld' => !(!$oldPurchaseProductVariant)
        ];
    }

    private function getPurchaseProductVariantFromPurchase(PurchaseProductVariant $purchaseProductVariant, Purchase $purchase): ?PurchaseProductVariant
    {
        foreach ($purchase->getProductVariants() as $ppv) {
            if ($ppv->getProductVariant()->getId() === $purchaseProductVariant->getProductVariant()->getId()) return $ppv;
        }
        return null;
    }

}