<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Address;
use Greendot\EshopBundle\Entity\Project\ClientAddress;

/**
 * @extends ServiceEntityRepository<ClientAddress>
 *
 * @method ClientAddress|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientAddress|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientAddress[]    findAll()
 * @method ClientAddress[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Address::class);
    }
}