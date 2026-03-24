<?php

namespace Greendot\EshopBundle\StructuredData\Service;

use Greendot\EshopBundle\StructuredData\Model\AbstractSchemaType;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Greendot\EshopBundle\StructuredData\Contract\StructuredDataProviderInterface;

/**
 * Manages the collection and aggregation of structured data models.
 */
class StructuredDataManager
{
    /** @var AbstractSchemaType[] */
    private array $models = [];

    /** @var iterable<StructuredDataProviderInterface> */
    private iterable $providers;

    public function __construct(
        #[TaggedIterator('greendot.structured_data_provider')]
        iterable $providers = [])
    {
        $this->providers = $providers;
    }

    /**
     * Adds a model directly to the collection.
     *
     * @param AbstractSchemaType $model
     * @return $this
     */
    public function addModel(AbstractSchemaType $model): self
    {
        $this->models[] = $model;
        return $this;
    }

    /**
     * Collects structured data from all supporting providers for a given object/context.
     *
     * @param mixed $object
     */
    public function collect(mixed $object = null): void
    {
        $providers = [];
        foreach ($this->providers as $provider) {
            $providers[] = $provider;
        }

        usort($providers, fn(StructuredDataProviderInterface $a, StructuredDataProviderInterface $b) => $b->getPriority() <=> $a->getPriority());

        foreach ($providers as $provider) {
            if ($provider->supports($object)) {
                $provided = $provider->provide($object);
                if ($provided === null) {
                    continue;
                }

                if ($provided instanceof AbstractSchemaType) {
                    $this->addModel($provided);
                } else if (is_array($provided)) {
                    foreach ($provided as $item) {
                        if ($item instanceof AbstractSchemaType) {
                            $this->addModel($item);
                        }
                    }
                }
            }
        }
    }

    /**
     * @return AbstractSchemaType[]
     */
    public function getModels(): array
    {
        return $this->models;
    }

    /**
     * Clears all collected models.
     */
    public function clear(): void
    {
        $this->models = [];
    }
}
