<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function emailAvailable($email) :bool
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select($qb->expr()->count('c.id'))
            ->where('c.email = :email')
            ->andWhere('c.isAnonymous = 0');
        if($qb->getQuery()->getSingleScalarResult() > 0){
            return false;
        }else{
            return true;
        }
    }
}
