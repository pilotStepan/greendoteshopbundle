<?php

namespace Greendot\EshopBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Greendot\EshopBundle\Entity\Project\Comment;
use Greendot\EshopBundle\Entity\Project\Transportation;

class CommentIsActiveFilter extends SQLFilter
{

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // check if transportation
        if ($targetEntity->getReflectionClass()->name !== Comment::class)
        {
            return "";
        }

        // filter
        return sprintf('%s.is_active = true', $targetTableAlias);
    }
}