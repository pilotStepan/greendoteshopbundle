<?php

namespace Greendot\EshopBundle\Message\Export;

class InitializeExportMessage
{
    public function __construct(
        public readonly int $exportId,
        public readonly string $alias,
        public readonly int $chunkSize = 100,
    ){}
}