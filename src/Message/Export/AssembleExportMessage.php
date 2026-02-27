<?php

namespace Greendot\EshopBundle\Message\Export;

class AssembleExportMessage
{
    public function __construct(
        public readonly int $exportId,
        public readonly string $alias,
    ){}
}