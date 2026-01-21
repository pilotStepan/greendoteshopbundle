<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Greendot\EshopBundle\Entity\Project\{Client, Purchase};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Doctrine\ORM\{QueryBuilder, NoResultException, NonUniqueResultException};

/**
 * @method Purchase|null find($id, $lockMode = null, $lockVersion = null)
 * @method Purchase|null findOneBy(array $criteria, array $orderBy = null)
 * @method Purchase[]    findAll()
 * @method Purchase[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseRepository extends ServiceEntityRepository
{
    public function __construct(
        private readonly RequestStack $requestStack,
        ManagerRegistry               $registry,
    )
    {
        parent::__construct($registry, Purchase::class);
    }

    public function lastInquiryOfUser($client)
    {
        return $this->createQueryBuilder('p')
            ->setMaxResults(1)
            ->orderBy("p.date_issue", "DESC")
            ->andWhere("p.state = :inquiry")->setParameter('inquiry', 'inquiry')
            ->andWhere("p.Client = :client")->setParameter("client", $client)
            ->getQuery()->getOneOrNullResult()
        ;
    }

    public function lastPurchaseOfUser($client)
    {
        return $this->createQueryBuilder('p')
            ->setMaxResults(1)
            ->orderBy('p.date_issue', "DESC")
            ->andWhere("p.state NOT IN (:excludedStates)")
            ->setParameter('excludedStates', ['inquiry', 'draft', 'wishlist'])
            ->andWhere("p.client = :client")->setParameter("client", $client)
            ->getQuery()->getOneOrNullResult()
        ;
    }

    public function getClientPurchases(Client $client)
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.date_issue', 'desc')
            ->andWhere("p.state NOT IN (:excludedStates)")
            ->setParameter('excludedStates', ['inquiry', 'draft', 'wishlist'])
            ->andWhere('p.client = :client')->setParameter('client', $client)
            ->getQuery()->getResult()
        ;
    }

    public function getClientDrafts(Client $client)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.client = :client')->setParameter('client', $client)
            ->andWhere('p.state = :state')->setParameter('state', 'draft')
            ->orderBy('p.date_issue', 'desc')
            ->getQuery()->getResult()
        ;
    }

    public function findNextInvoiceNumber(): ?string
    {
        $connection = $this->getEntityManager()->getConnection();
        $sql = 'SELECT MAX(CAST(invoice_number AS UNSIGNED)) AS max_invoice FROM purchase';
        $maxInvoiceNumber = $connection->fetchOne($sql);

        return $maxInvoiceNumber ? (string)($maxInvoiceNumber + 1) : '1';
    }

    public function findBySession(QueryBuilder $queryBuilder): QueryBuilder
    {
        $alias = $queryBuilder->getRootAliases()[0];

        try {
            $session = $this->requestStack->getCurrentRequest()?->getSession();
        } catch (SessionNotFoundException $e) {
            $session = null;
        }

        if ($session?->has('purchase')) {
            $purchaseId = $session->get('purchase');
        } else {
            $purchaseId = 0;
        }


        return $queryBuilder
            ->andWhere(sprintf('%s.id = :purchaseId', $alias))
            ->setParameter('purchaseId', $purchaseId)
        ;
    }

    public function findOneBySession(): ?Purchase
    {
        $qb = $this->createQueryBuilder('p');

        try {
            $session = $this->requestStack->getCurrentRequest()?->getSession();
        } catch (SessionNotFoundException $e) {
            return null;
        }

        if ($session?->has('purchase')) {
            $purchaseId = $session->get('purchase');
            $qb->andWhere('p.id = :purchaseId')
                ->setParameter('purchaseId', $purchaseId)
            ;
            return $qb->getQuery()->getOneOrNullResult();
        } else {
            return null;
        }
    }

    public function findWishlistBySession(): ?Purchase
    {
        $qb = $this->createQueryBuilder('p');
        $session = $this->requestStack->getCurrentRequest()->getSession();
        if ($session->has('wishlist')) {
            $purchaseId = $session->get('wishlist');
            $qb->andWhere('p.id = :purchaseId')
                ->setParameter('purchaseId', $purchaseId)
            ;
            return $qb->getQuery()->getOneOrNullResult();
        } else {
            return null;
        }
    }

    public function findCartForClient(?Client $client): ?Purchase
    {
        if (!$client) return null;

        return $this->createQueryBuilder('p')
            ->andWhere('p.client = :client')
            ->andWhere('p.state IN (:states)')
            ->setParameter('client', $client)
            ->setParameter('states', ['draft'])
            ->orderBy('p.date_issue', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findWishlistForClient(?Client $client): ?Purchase
    {
        if (!$client) return null;

        return $this->createQueryBuilder('p')
            ->andWhere('p.client = :client')
            ->andWhere('p.state = :state')
            ->setParameter('client', $client)
            ->setParameter('state', 'wishlist')
            ->orderBy('p.date_issue', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @throws NonUniqueResultException|NoResultException
     */
    public function findByPaymentId(string $paymentId): Purchase
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.paymentId = :paymentId')
            ->setParameter('paymentId', $paymentId)
            ->getQuery()
            ->getSingleResult()
        ;
    }
}
