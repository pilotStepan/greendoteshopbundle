<?php

namespace Greendot\EshopBundle\Service\DataLayer;

use Symfony\Component\HttpFoundation\RequestStack;

class DataLayerManager
{
    private array $events = [];
    private const SESSION_KEY = '_gtm_datalayer_events';

    public function __construct(private readonly RequestStack $requestStack){}

    /**
     * Every event with an `ecommerce` key gets its own `{ecommerce: null}` reset right before it, in the same
     * storage, so GTM never merges items[] of consecutive events. A bare reset pushed by a caller is ignored.
     */
    public function push(array $data, bool $persist = false): void
    {
        if ($data === ['ecommerce' => null]) return;

        if (array_key_exists('ecommerce', $data)) {
            $this->store(['ecommerce' => null], $persist);
        }
        $this->store($data, $persist);
    }

    private function store(array $data, bool $persist): void
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