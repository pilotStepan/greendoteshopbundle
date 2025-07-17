<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Greendot\EshopBundle\Service\ShortCodes\ShortCodeBase;
use Greendot\EshopBundle\Service\ShortCodes\ShortCodeProvider;

#[AsDoctrineListener(Events::postLoad, priority: 100)]
class ShortCodeReplaceSubscriber
{
    public function __construct(
        private readonly ShortCodeProvider $shortCodeProvider
    )
    {
    }

    public function postLoad(LifecycleEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getObject();
        $entityClass = get_class($entity);

        $providers = $this->shortCodeProvider->getSupported($entityClass);
        foreach ($providers as $provider) {
            assert(is_subclass_of($provider, ShortCodeBase::class));
            $provider->replace($entity);
        }

    }


}