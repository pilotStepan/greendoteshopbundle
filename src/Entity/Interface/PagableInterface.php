<?php

namespace Greendot\EshopBundle\Entity\Interface;


Interface PagableInterface
{
    public function getName() : ?string;

    public function getSlug() : ?string;
}