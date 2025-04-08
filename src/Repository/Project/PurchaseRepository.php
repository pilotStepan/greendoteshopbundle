<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method Purchase|null find($id, $lockMode = null, $lockVersion = null)
 * @method Purchase|null findOneBy(array $criteria, array $orderBy = null)
 * @method Purchase[]    findAll()
 * @method Purchase[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseRepository extends ServiceEntityRepository
{
    public function __construct(
        private readonly RequestStack           $requestStack,
        private readonly EntityManagerInterface $entityManager,
        ManagerRegistry                         $registry,
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
            ->getQuery()->getOneOrNullResult();
    }

    public function lastPurchaseOfUser($client)
    {
        return $this->createQueryBuilder('p')
            ->setMaxResults(1)
            ->orderBy('p.date_issue', "DESC")
            ->andWhere("p.state != :inquiry")->setParameter('inquiry', 'inquiry')
            ->andWhere("p.client = :client")->setParameter("client", $client)
            ->getQuery()->getOneOrNullResult();
    }

    public function getClientPurchases(Client $client)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.client = :client')->setParameter('client', $client)
            ->andWhere('p.state != :state')->setParameter('state', 'inquiry')
            ->orderBy('p.date_issue', 'desc')
            ->getQuery()->getResult();
    }

    public function getNextInvoiceNumber(): ?int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('MAX(CAST(p.invoice_number AS int)) as max_invoice_number');

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? (int)$result + 1 : 1;
    }

    public function findBySession(QueryBuilder $queryBuilder): QueryBuilder
    {
        $alias = $queryBuilder->getRootAliases()[0];
        $session = $this->requestStack->getCurrentRequest()->getSession();
        if ($session->has('purchase')) {
            $purchaseId = $session->get('purchase');
        } else {
            $purchaseId = 0;
        }


        return $queryBuilder
            ->andWhere(sprintf('%s.id = :purchaseId', $alias))
            ->setParameter('purchaseId', $purchaseId);
    }

    public function findOneBySession(string $type): Purchase|null
    {
        $qb = $this->createQueryBuilder('p');
        $session = $this->requestStack->getCurrentRequest()->getSession();
        if ($session->has('purchase')) {
            $purchaseId = $session->get('purchase');
            $qb->andWhere('p.id = :purchaseId')
                ->setParameter('purchaseId', $purchaseId);
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
            ->setParameter('states', ['draft', 'new'])
            ->orderBy('p.date_issue', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
