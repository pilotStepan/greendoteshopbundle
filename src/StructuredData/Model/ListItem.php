<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org ListItem model.
 * @link https://schema.org/ListItem
 */
class ListItem extends AbstractSchemaType
{
    protected ?int $position = null;
    protected ?string $name = null;
    /** @var string|AbstractSchemaType|null */
    protected $item = null;

    public function getType(): string
    {
        return 'ListItem';
    }

    public function getPosition(): ?int { return $this->position; }

    public function setPosition(?int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getName(): ?string { return $this->name; }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getItem() { return $this->item; }

    public function setItem($item): self
    {
        $this->item = $item;
        return $this;
    }
}
