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


    public function getStateMetadata(mixed $entity): ?array
    {
        // Use publicOnly to exclude internal funnel places (log_track_done, pay_track_done, etc.)
        // which are always active alongside display places in the parallel marking.
        $places = $this->getPlacesMetadata($entity, publicOnly: true);
        if ($places === null || count($places) !== 1) {
            return null;
        }
        return array_values($places)[0];
    }

    public function getPlacesMetadata(mixed $entity, bool $publicOnly = false): ?array
    {
        if (!$this->registry->has($entity)) {
            return null;
        }

        $workflow = $this->registry->get($entity);
        $metadataStore = $workflow->getMetadataStore();
        $activePlaces = array_keys($workflow->getMarking($entity)->getPlaces());

        $result = [];
        foreach ($activePlaces as $placeName) {
            $metadata = $metadataStore->getPlaceMetadata($placeName);
            if ($publicOnly && !isset($metadata['customer_label'])) {
                continue;
            }
            $result[$placeName] = array_merge(['place' => $placeName], $metadata);
        }

        return $result;
    }

    public function getPlacesMetadataByTrack(mixed $entity, bool $publicOnly = false): ?array
    {
        $places = $this->getPlacesMetadata($entity, $publicOnly);
        if ($places === null) {
            return null;
        }

        $grouped = [];
        foreach ($places as $metadata) {
            $track = $metadata['track'] ?? '_default';
            $grouped[$track] = $metadata;
        }

        return $grouped;
    }
}