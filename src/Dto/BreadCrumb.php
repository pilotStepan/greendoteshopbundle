<?php

namespace Greendot\EshopBundle\Dto;


class BreadCrumb
{
    public function __construct(
        public ?string $name,
        public ?string $link,
    ){}
}