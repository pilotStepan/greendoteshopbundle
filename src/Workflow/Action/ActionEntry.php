<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

/**
 * One validated entry of a transition's `actions` (or legacy `notifications`) metadata.
 */
final readonly class ActionEntry
{
    public function __construct(
        public string     $key,
        public ActionKind $kind,
        public bool       $skippable,
    ) {}
}
