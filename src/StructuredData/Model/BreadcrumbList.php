<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org BreadcrumbList model.
 * @link https://schema.org/BreadcrumbList
 */
class BreadcrumbList extends AbstractSchemaType
{
    /** @var ListItem[]|null */
    protected ?array $itemListElement = null;

    public function getType(): string
    {
        return 'BreadcrumbList';
    }

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
