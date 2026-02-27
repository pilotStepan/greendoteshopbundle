<?php

namespace Greendot\EshopBundle\Export\Data\Model;

class GoogleProductFeedModel
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $link,
        public ?string $price,
        public ?string $salePrice,
        public string $condition,
        public string $ageGroup,
        public ?string $brand,
        public ?string $mpn,
        public bool $identifier_exists,
        public ?string $image_link,
        public string $availability,
        public ?string $productType,
        public string $itemGroupId
    ){}
}