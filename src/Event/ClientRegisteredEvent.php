<?php

namespace Greendot\EshopBundle\Event;

use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Contracts\EventDispatcher\Event;

final class ClientRegisteredEvent extends Event
{
    public function __construct(
        public readonly Client $client,
    ) {}
}