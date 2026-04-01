<?php

namespace Greendot\EshopBundle\Schema;

use Spatie\SchemaOrg\BaseType;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class SchemaRegistry
{
    /** @var BaseType[] */
    private array $schemas = [];

    /** @var iterable<SchemaProviderInterface> */
    private iterable $providers;

    public function __construct(
        #[AutowireIterator('greendot.schema_provider')]
        iterable $providers = [],
    )
    {
        $providersArray = is_array($providers)
            ? $providers
            : iterator_to_array($providers);

        usort($providersArray, fn(SchemaProviderInterface $a, SchemaProviderInterface $b) => $b->getPriority() <=> $a->getPriority());

        $this->providers = $providersArray;
    }

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

    private function add(BaseType $schema): void
    {
        if (!in_array($schema, $this->schemas, true)) {
            $this->schemas[] = $schema;
        }
    }

    public function render(): string
    {
        if ($this->schemas === []) {
            return '';
        }

        $payload = array_map(
            static fn(BaseType $schema): array => $schema->toArray(),
            $this->schemas,
        );

        $json = json_encode(
            count($payload) === 1 ? $payload[0] : $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return sprintf(
            "<script type=\"application/ld+json\">\n%s\n</script>",
            $json,
        );
    }
}
