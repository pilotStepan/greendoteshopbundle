<?php

namespace Greendot\EshopBundle\Twig;

use Greendot\EshopBundle\Service\ManageWorkflows;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class WorkflowExtension extends AbstractExtension
{
    private $manageWorkflows;

    public function __construct(ManageWorkflows $manageWorkflows)
    {
        $this->manageWorkflows = $manageWorkflows;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_state_metadata', [$this, 'getStateMetadata']),
            new TwigFunction('get_places_metadata', [$this, 'getPlacesMetadata']),
            new TwigFunction('get_places_metadata_by_track', [$this, 'getPlacesMetadataByTrack']),
            new TwigFunction('get_public_places_metadata_by_track', [$this, 'getPublicPlacesMetadataByTrack']),
        ];
    }

    public function getStateMetadata($object): ?array
    {
        return $this->manageWorkflows->getStateMetadata($object);
    }

    public function getPlacesMetadata($object): ?array
    {
        return $this->manageWorkflows->getPlacesMetadata($object, publicOnly: true);
    }

    public function getPlacesMetadataByTrack($object): ?array
    {
        return $this->manageWorkflows->getPlacesMetadataByTrack($object, publicOnly: true);
    }

    public function getPublicPlacesMetadataByTrack($object): ?array
    {
        return $this->manageWorkflows->getPlacesMetadataByTrack($object, publicOnly: true);
    }
}