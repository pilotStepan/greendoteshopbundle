<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org WebPage model.
 * @link https://schema.org/WebPage
 */
class WebPage extends AbstractSchemaType
{
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?string $url = null;
    /** @var mixed|null */
    protected $mainEntity = null;

    public function getType(): string
    {
        return 'WebPage';
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): self { $this->url = $url; return $this; }

    public function getMainEntity() { return $this->mainEntity; }
    public function setMainEntity($mainEntity): self { $this->mainEntity = $mainEntity; return $this; }
}
