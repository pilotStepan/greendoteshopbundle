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
        ];
    }

    public function getStateMetadata($object): ?array
    {
        return $this->manageWorkflows->getStateMetadata($object);
    }
}