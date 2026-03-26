<?php

namespace App\Schema;

use Spatie\SchemaOrg\BaseType;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class SchemaManager
{
    /** @var BaseType[] */
    private array $schemas = [];
    /** @var iterable<SchemaProviderInterface> */
    private iterable $providers;

    public function __construct(
        #[AutowireIterator('greendot.schema_provider')]
        iterable $providers = [])
    {
        usort($providers, fn(SchemaProviderInterface $a, SchemaProviderInterface $b) => $b->getPriority() <=> $a->getPriority());
        $this->providers = $providers;
    }

    /**
     * Collects structured data from all supporting providers for a given object/context.
     */
    public function collect(mixed $object = null): void
    {
        foreach ($this->providers as $provider) {
            if (!$provider->supports($object)) {
                continue;
            }

            $provided = $provider->provide($object);
            $this->add($provided);
        }
    }

    public function render(): string
    {
        $output = array_map(
            static fn(BaseType $schema): string => $schema->toScript(),
            $this->schemas,
        );

        return implode("\n", $output);
    }

    private function add(BaseType $schema): void
    {
        if (!in_array($schema, $this->schemas, true)) {
            $this->schemas[] = $schema;
        }
    }
}

