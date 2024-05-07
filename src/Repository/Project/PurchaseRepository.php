<?php

namespace Greendot\EshopBundle\Repository\Project;

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
    public function __construct(ManagerRegistry $registry, RequestStack $requestStack)
    {
        parent::__construct($registry, Purchase::class);
        $this->requestStack = $requestStack;
    }

    public function lastInquiryOfUser($client){
        return $this->createQueryBuilder('p')
            ->setMaxResults(1)
            ->orderBy("p.date_issue", "DESC")
            ->andWhere("p.state = :inquiry")->setParameter('inquiry', 'inquiry')
            ->andWhere("p.Client = :client")->setParameter("client", $client)
            ->getQuery()->getOneOrNullResult();
    }

    public function lastPurchaseOfUser($client){
        return $this->createQueryBuilder('p')
            ->setMaxResults(1)
            ->orderBy('p.date_issue', "DESC")
            ->andWhere("p.state != :inquiry")->setParameter('inquiry', 'inquiry')
            ->andWhere("p.Client = :client")->setParameter("client", $client)
            ->getQuery()->getOneOrNullResult();
    }

    public function getClientPurchases(Client $client){
        return $this->createQueryBuilder('p')
            ->andWhere('p.Client = :client')->setParameter('client', $client)
            ->andWhere('p.state != :state')->setParameter('state', 'inquiry')
            ->orderBy('p.date_issue', 'desc')
            ->getQuery()->getResult();
    }
}
