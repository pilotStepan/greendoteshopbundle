<?php

namespace Greendot\EshopBundle\Invoice\Data;

class VatCategoryData
{
    public function __construct(
        public float    $percentage,
        public float    $base,
        public float    $baseSecondary,
        public float    $value,
        public float    $valueSecondary,        
    ) { }
}