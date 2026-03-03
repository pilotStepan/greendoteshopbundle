<?php

namespace Greendot\EshopBundle\StructuredData\Model;

use JsonSerializable;

/**
 * Base class for all Schema.org types.
 */
abstract class AbstractSchemaType implements JsonSerializable
{
    /** @var string|null */
    protected ?string $id = null;

    /** @var string|null */
    protected ?string $context = 'https://schema.org';

    /**
     * @return string
     */
    abstract public function getType(): string;

    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string|null $id
     * @return $this
     */
    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getContext(): ?string
    {
        return $this->context;
    }

    /**
     * @param string|null $context
     * @return $this
     */
    public function setContext(?string $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Serializes the object to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->context !== null) {
            $data['@context'] = $this->context;
        }

        $data['@type'] = $this->getType();

        if ($this->id !== null) {
            $data['@id'] = $this->id;
        }

        // Get all public/protected properties through reflection or getters
        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            if (in_array($name, ['id', 'context'])) {
                continue;
            }

            $getter = 'get' . ucfirst($name);
            if (method_exists($this, $getter)) {
                $value = $this->$getter();
            } else {
                $property->setAccessible(true);
                $value = $property->getValue($this);
            }

            if ($value !== null) {
                $data[$name] = $value;
            }
        }

        return $data;
    }
}
