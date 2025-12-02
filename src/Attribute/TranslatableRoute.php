<?php

namespace Greendot\EshopBundle\Attribute;

use Attribute;
#[\Attribute(\Attribute::TARGET_METHOD)]
class TranslatableRoute
{
    public function __construct(
        public string $class,
        public string $property = 'slug',
    ) {}
}