<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Repository\Project\ColourRepository;
use Greendot\EshopBundle\Service\ListenerManager;

class ParameterEventListener
{
    public function __construct(
        private ColourRepository    $colourRepository,
        private ListenerManager     $listenerManager,
    ) { }

    public function postLoad(LifecycleEventArgs $args): void
    { 
        $entity = $args->getObject();

        if (!$this->supports($entity)) {
            return;
        }

        $paramData = $entity->getData();
        
        // if param is color, try to find color name and add it
        if ($entity?->getParameterGroup()?->getParameterGroupFilterType()?->getName() === "color"){
            $hex = str_starts_with($paramData, '#') ? $paramData : '#' . $paramData;
            $color = $this->colourRepository->findOneBy(['hex'=>$hex]);
            if ($color){
                $colorName = $color->getName();
                $entity->setColorName($colorName);
            }
        }
        
    }


    public function supports($entity) : bool
    {
        return $entity instanceof Parameter && !$this->listenerManager->isDisabled(self::class);
    }
}