<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Message\Algolia;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class FlushProductIndex
{
    public function __construct(public string $indexName) {}
}
