<?php

namespace Greendot\EshopBundle\DataLayer\Event;

use Greendot\EshopBundle\Entity\Project\Category;
use Symfony\Contracts\EventDispatcher\Event;

class ViewItemListEvent extends Event
{
    private ?array $productIds = null;
    private ?string $productFetchUri = null;

    public function __construct(
        private Category $category,
        ?string $productFetchUri = null,
        ?array $productIds = null
    ){
        if ($productIds === null && $productFetchUri === null) throw new \Exception('ProductIds or productFetchUri must be set');
        $this->productFetchUri = $productFetchUri;
        $this->productIds = $productIds;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function getProductIds(): ?array
    {
        return $this->productIds;
    }

    public function getProductFetchUri(): ?string
    {
        return $this->productFetchUri;
    }


}