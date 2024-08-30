<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\InformationBlock;
use Greendot\EshopBundle\Repository\Project\InformationBlockRepository;
use Exception;

class InformationBlockService
{
    public function __construct(private readonly InformationBlockRepository $informationBlockRepository)
    {
    }

    /**
     * @return InformationBlock[]
     * @throws Exception
     */
    public function getInformationBlocksForEntity($entity,bool $onlyActive = true, int $informationBlockTypeID = null): array
    {
        return $this->informationBlockRepository->getInformationBlocksForEntity($entity, $onlyActive, $informationBlockTypeID);
    }
}