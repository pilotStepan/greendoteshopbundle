<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org ItemList model.
 * @link https://schema.org/ItemList
 */
class ItemList extends AbstractSchemaType
{
    protected ?string $name = null;
    /** @var ListItem[]|null */
    protected ?array $itemListElement = null;

    public function getType(): string
    {
        return 'ItemList';
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }

    /**
     * @return ListItem[]|null
     */
    public function getItemListElement(): ?array { return $this->itemListElement; }

    /**
     * @param ListItem[]|null $itemListElement
     * @return $this
     */
    public function setItemListElement(?array $itemListElement): self { $this->itemListElement = $itemListElement; return $this; }
}
