<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsPurchaseNotification
{
    public function __construct(public string $alias) {}
}
