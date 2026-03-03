<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org Brand model.
 * @link https://schema.org/Brand
 */
class Brand extends AbstractSchemaType
{
    protected ?string $name = null;

    public function getType(): string
    {
        return 'Brand';
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }
}
