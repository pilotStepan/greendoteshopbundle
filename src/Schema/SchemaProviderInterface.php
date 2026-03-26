<?php

namespace Greendot\EshopBundle\Schema;

use Spatie\SchemaOrg\BaseType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('greendot.schema_provider')]
interface SchemaProviderInterface
{
    /**
     * Checks if this provider can provide data for the given object.
     *
     * @param mixed $object The domain entity or context.
     * @return bool
     */
    public function supports(mixed $object): bool;

    /**
     * Generates structured data (a Schema.org model or array) for the given object.
     *
     * @param mixed $object The domain entity or context.
     * @return BaseType
     * @throws UnsupportedSchemaSubjectException if the provided object is not supported.
     */
    public function provide(mixed $object): BaseType;

    /**
     * Priority for the provider. Higher priority providers are called first.
     *
     * @return int
     */
    public function getPriority(): int;
}
