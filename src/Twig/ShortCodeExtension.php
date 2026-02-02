<?php

namespace Greendot\EshopBundle\Twig;

use Greendot\EshopBundle\Service\ShortCodes\ShortCodeBase;
use Greendot\EshopBundle\Service\ShortCodes\ShortCodeProvider;
use Twig\Attribute\AsTwigFilter;

class ShortCodeExtension
{
    public function __construct(
        private readonly ShortCodeProvider $shortCodeProvider
    ) {
    }

    #[AsTwigFilter('short_code_replace')]
    public function shortCodeReplace(object $entity, string $field): object
    {
        $entityClass = get_class($entity);

        $providers = $this->shortCodeProvider->getSupportedByField($entityClass, $field);
        foreach ($providers as $provider) {
            assert(is_subclass_of($provider, ShortCodeBase::class));
            $provider->replaceField($entity, $field);
        }
        return $entity;
    }
}