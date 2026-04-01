<?php

namespace Greendot\EshopBundle\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

trait MainCategoryTrait
{
    #[ORM\Column(options: ['default' => false])]
    private bool $isMainCategory = false;

    public function isisMainCategory(): bool
    {
        return $this->isMainCategory;
    }

    public function setisMainCategory(bool $isMainCategory): static
    {
        $this->isMainCategory = $isMainCategory;

        return $this;
    }
}
