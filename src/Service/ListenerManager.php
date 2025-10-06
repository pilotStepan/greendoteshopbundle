<?php

namespace Greendot\EshopBundle\Service;

class ListenerManager 
{
     private array $disabledListeners = [];

    public function disable(string $listenerClass): void
    {
        $this->disabledListeners[$listenerClass] = true;
    }

    public function enable(string $listenerClass): void
    {
        unset($this->disabledListeners[$listenerClass]);
    }

    public function isDisabled(string $listenerClass): bool
    {
        return $this->disabledListeners[$listenerClass] ?? false;
    }

    public function disableAll(array $listenerClasses): void
    {
        foreach ($listenerClasses as $class) {
            $this->disable($class);
        }
    }

    public function enableAll(array $listenerClasses): void
    {
        foreach ($listenerClasses as $class) {
            $this->enable($class);
        }
    }
}