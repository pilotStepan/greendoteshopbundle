<?php

namespace Greendot\EshopBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class DisableListeners
{
    public array $listenerClasses;

    public function __construct(array $listenerClasses)
    {
        $this->listenerClasses = $listenerClasses;
    }
}
