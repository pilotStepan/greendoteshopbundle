<?php

namespace Greendot\EshopBundle\Entity\Interface;

interface SoftDeletedInterface
{
    public function isIsDeleted() : bool;

    public function setIsDeleted(bool $isDeleted) : static;
}
