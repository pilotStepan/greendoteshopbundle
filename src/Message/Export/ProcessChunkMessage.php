<?php

namespace Greendot\EshopBundle\Message\Export;

class ProcessChunkMessage
{
    public function __construct(
        public readonly int $exportId,
        public readonly string $alias,
        public readonly int $offset,
        public readonly int $limit,
    ){}
}