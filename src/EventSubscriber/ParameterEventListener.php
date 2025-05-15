<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Repository\Project\ColourRepository;


class ParameterEventListener
{
    public function __construct(
        private ColourRepository $colourRepository
    )
    {
    }

    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Parameter) {
            $paramData = $entity->getData();

            // if param is color
            if ((strlen($paramData) === 6 && ctype_xdigit($paramData))){
                $hex = '#'.$paramData;
                // try to find color name and add it
                $color = $this->colourRepository->findOneBy(['hex'=>$hex]);
                if ($color){
                    $colorName = $color->getName();
                    $entity->setColorName($colorName);
                }
            }
        }
    }
}