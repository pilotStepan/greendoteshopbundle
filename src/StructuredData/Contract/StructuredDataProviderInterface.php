<?php

namespace Greendot\EshopBundle\StructuredData\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for providing structured data for specific entities.
 * Each project can implement this to map their domain entities to Schema.org models.
 */
#[AutoconfigureTag('greendot.structured_data_provider')]
interface StructuredDataProviderInterface
{
    /**
     * Checks if this provider can provide data for the given object.
     *
     * @param mixed $object The domain entity or context.
     * @return bool
     */
    public function supports(mixed $object): bool;

    /**
     * Provides structured data (a Schema.org model or array) for the given object.
     *
     * @param mixed $object The domain entity or context.
     * @return object|array|null
     */
    public function provide(mixed $object): object|array|null;

    /**
     * Priority for the provider. Higher priority providers are called first.
     *
     * @return int
     */
    public function getPriority(): int;
}
