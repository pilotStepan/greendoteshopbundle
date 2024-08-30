<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;

class ManageWorkflows
{
    public function __construct(
            private Registry $registry
    ){}

    //finds the transition by final state or return null if none is found
    public function findTransitionByFinalState(string $newState, Object $object): ?Transition
    {
        $workflow = $this->registry->get($object);

        $transitions = $workflow->getEnabledTransitions($object);

        foreach ($transitions as $transition){
            if ($transition->getTos()[0] === $newState){
                return $transition;
            }
        }

        return null;
    }


    public function getStateMetadata(mixed $entity):?array
    {
        if ($this->registry->has($entity)){
            $workflow = $this->registry->get($entity);
            $metadataStore = $workflow->getMetadataStore();
            $currentPlace = $workflow->getMarking($entity)->getPlaces();
            $currentPlace = array_keys($currentPlace);
            if (count($currentPlace) === 1){
                $currentPlace = $currentPlace[0];
            }else{
                return null;
            }
            $metadata = $metadataStore->getPlaceMetadata($currentPlace);
            return $metadata;
        }else{
            return null;
        }
    }
}