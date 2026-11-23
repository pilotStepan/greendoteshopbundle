<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

final readonly class ResolvedAction
{
    public function __construct(
        public ActionEntry       $entry,
        public ActionDescription $description,
    ) {}
}
