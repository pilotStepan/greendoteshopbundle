<?php

namespace Greendot\EshopBundle\Entity\Interface;


interface PageableInterface
{
    public function getControllerName(): string;

    public function getSlug(): string;

    public function getTitle(): ?string;

    public function getDescription(): ?string;
}