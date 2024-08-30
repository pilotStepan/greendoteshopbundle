<?php
namespace Greendot\EshopBundle\Entity\Entity;


use Algolia\SearchBundle\Entity\Aggregator;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AlgoliaProduct extends Aggregator
{


    public static function getEntities(): array
    {
        return [
            Product::class,
            ProductVariant::class
        ];
    }
}