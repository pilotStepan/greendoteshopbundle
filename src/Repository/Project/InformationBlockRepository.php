<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Event;
use Greendot\EshopBundle\Entity\Project\InformationBlock;
use Greendot\EshopBundle\Entity\Project\Person;
use Greendot\EshopBundle\Entity\Project\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use function PHPUnit\Framework\throwException;

/**
 * @extends ServiceEntityRepository<InformationBlock>
 *
 * @method InformationBlock|null find($id, $lockMode = null, $lockVersion = null)
 * @method InformationBlock|null findOneBy(array $criteria, array $orderBy = null)
 * @method InformationBlock[]    findAll()
 * @method InformationBlock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InformationBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InformationBlock::class);
    }

    /**
     * @return InformationBlock[] Returns an array of InformationBlock objects
     * @throws Exception
     */
    public function getInformationBlocksForEntity($entity, bool $onlyActive = true, int $informationBlockType = null): array
    {
        $qb = $this->createQueryBuilder('i');
        if ($onlyActive){
            $qb->andWhere('i.isActive = :active')->setParameter('active', true);
        }
        if ($informationBlockType){
            $qb->leftJoin('i.informationBlockType', 'ibt')
                ->andWhere('ibt.id = :ibt_id')
                ->setParameter('ibt_id', $informationBlockType);
        }
        if ($entity instanceof Category){
            $qb->select('i')
                ->leftJoin('i.categoryInformationBlocks', 'cib')
                ->andWhere('cib.category = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('cib.sequence', 'ASC');

        } elseif ($entity instanceof Product){
            $qb->select('i')
                ->leftJoin('i.productInformationBlocks', 'pib')
                ->wheandWherere('pib.product = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('pib.sequence', 'ASC');

        } elseif ($entity instanceof Person){
            $qb->select('i')
                ->leftJoin('i.personInformationBlocks', 'pib')
                ->andWhere('pib.person = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('pib.sequence', 'ASC');

        } elseif ($entity instanceof Event){
            $qb->select('i')
                ->leftJoin('i.eventInformationBlocks', 'eib')
                ->andWhere('eib.event = :entity')
                ->setParameter('entity', $entity)
                ->addOrderBy('eib.sequence', 'ASC');
        } else {
            throw new Exception('Unknown entity type');
        }

        return $qb->getQuery()->getResult();
    }
}
