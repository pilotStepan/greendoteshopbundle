<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Label;
use Greendot\EshopBundle\Repository\HintedRepositoryBase;

/**
 * @method Label|null find($id, $lockMode = null, $lockVersion = null)
 * @method Label|null findOneBy(array $criteria, array $orderBy = null)
 * @method Label[]    findAll()
 * @method Label[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LabelRepository extends HintedRepositoryBase
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Label::class);
    }
}
