<?php

namespace Greendot\EshopBundle\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

trait SoftDeletedTrait
{
    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    public function isIsDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $deleted): static
    {
        $this->isDeleted = $deleted;

        return $this;
    }
}
