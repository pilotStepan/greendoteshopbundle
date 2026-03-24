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

    /** @var array */
    protected array $customProperties = [];

    /**
     * @return string
     */
    abstract public function getType(): string;

    /**
     * Sets a custom property not explicitly defined in the class.
     *
     * @param string $name
     * @param mixed  $value
     * @return $this
     */
    public function setProperty(string $name, mixed $value): self
    {
        $this->customProperties[$name] = $value;
        return $this;
    }

    public function getId(): ?string { return $this->id; }

    public function setId(?string $id): self
    {
        $this->id = $id;
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

        $data['@type'] = $this->getType();

        if ($this->id !== null) {
            $data['@id'] = $this->id;
        }

        // Get all public/protected properties
        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            if (in_array($name, ['id', 'customProperties'])) {
                continue;
            }

            $getter = 'get' . ucfirst($name);
            if (method_exists($this, $getter)) {
                $value = $this->$getter();
            } else {
                $value = $property->getValue($this);
            }

            if ($value !== null) {
                $data[$name] = $value;
            }
        }

        // Merge custom properties
        return array_merge($data, $this->customProperties);
    }
}
