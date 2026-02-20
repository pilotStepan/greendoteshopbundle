<?php

namespace Greendot\EshopBundle\Service\DataLayer;

use Symfony\Component\HttpFoundation\RequestStack;

class DataLayerManager
{
    private array $events = [];
    private const SESSION_KEY = '_gtm_datalayer_events';

    public function __construct(private readonly RequestStack $requestStack){}

    public function push(array $data, bool $persist = false): void
    {
        if (!$persist){
            $this->events[] = $data;
            return;
        }
        $session = $this->requestStack->getSession();
        $persisted = $session->get(self::SESSION_KEY, []);
        $persisted[] = $data;
        $session->set(self::SESSION_KEY, $persisted);
    }

    public function all(): array
    {
        $session = $this->requestStack->getSession();
        $persisted = $session->get(self::SESSION_KEY, []);
        $session->remove(self::SESSION_KEY);
        return array_merge($this->events, $persisted);
    }

}