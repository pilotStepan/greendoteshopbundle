<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;

/**
 * An issued purchase is a record: what it references must still load after the catalog hides it
 * (a disabled transportation or payment type, an inactive product or variant, a soft-deleted currency).
 *
 * Doctrine SQL filters apply to every query, including the one that initializes a lazy reference, so
 * `$purchase->getTransportation()->getName()` throws EntityNotFoundException once that transportation is
 * disabled: in invoices, e-mails, parcels, the CMS API, anywhere. This listener initializes such references
 * with only the filters that hide them suspended, as soon as an issued purchase or one of its lines is loaded.
 *
 * It covers every enabled filter, the bundle's and the tenant's, except filters with parameters: those scope
 * data (tenancy, locale) rather than hide catalog rows.
 *
 * Carts are left alone: the storefront must not offer what the catalog hides.
 * Only to-one references are covered. A DQL join on a filtered entity still applies the filter to the join.
 */
#[AsEntityListener(event: Events::postLoad, method: 'postLoadPurchase', entity: Purchase::class)]
#[AsEntityListener(event: Events::postLoad, method: 'postLoadPurchaseProductVariant', entity: PurchaseProductVariant::class)]
final class IssuedPurchaseReferencesListener
{
    /**
     * How far references are followed: PurchaseProductVariant -> ProductVariant -> Product.
     */
    private const MAX_DEPTH = 2;

    private const NO_PARAMETERS = 'a:0:{}';

    public function postLoadPurchase(Purchase $purchase, PostLoadEventArgs $args): void
    {
        if (!$purchase->isIssued()) {
            return;
        }

        $this->loadHiddenReferences($purchase, $args->getObjectManager(), self::MAX_DEPTH);
    }

    public function postLoadPurchaseProductVariant(PurchaseProductVariant $line, PostLoadEventArgs $args): void
    {
        $purchase = $line->getPurchase();
        if ($purchase === null || !$purchase->isIssued()) {
            return;
        }

        $this->loadHiddenReferences($line, $args->getObjectManager(), self::MAX_DEPTH);
    }

    private function loadHiddenReferences(object $entity, EntityManagerInterface $em, int $depth): void
    {
        $metadata = $em->getClassMetadata($entity::class);
        $unitOfWork = $em->getUnitOfWork();

        foreach ($metadata->getAssociationNames() as $field) {
            if (!$metadata->isSingleValuedAssociation($field)) {
                continue;
            }

            $reference = $metadata->getFieldValue($entity, $field);
            if (!is_object($reference)) {
                continue;
            }

            $hidingFilters = $this->filtersHiding($metadata->getAssociationTargetClass($field), $em);
            if ($hidingFilters === []) {
                continue;
            }

            if ($unitOfWork->isUninitializedObject($reference)) {
                $this->initializeWithout($reference, $hidingFilters, $em);
            }

            if ($depth > 1 && !$unitOfWork->isUninitializedObject($reference)) {
                $this->loadHiddenReferences($reference, $em, $depth - 1);
            }
        }
    }

    /**
     * @param list<string> $filterNames
     */
    private function initializeWithout(object $reference, array $filterNames, EntityManagerInterface $em): void
    {
        $filters = $em->getFilters();
        foreach ($filterNames as $name) {
            $filters->suspend($name);
        }

        try {
            $em->getUnitOfWork()->initializeObject($reference);
        } catch (EntityNotFoundException) {
            // The row is really gone, not just hidden. Leave the reference lazy: it fails where it is used, as before.
        } finally {
            foreach ($filterNames as $name) {
                $filters->restore($name);
            }
        }
    }

    /**
     * @param class-string $class
     *
     * @return list<string> names of the enabled, parameterless filters that hide rows of $class
     */
    private function filtersHiding(string $class, EntityManagerInterface $em): array
    {
        $targetMetadata = $em->getClassMetadata($class);
        $names = [];
        foreach ($em->getFilters()->getEnabledFilters() as $name => $filter) {
            if ((string) $filter === self::NO_PARAMETERS
                && $filter->addFilterConstraint($targetMetadata, 't') !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
