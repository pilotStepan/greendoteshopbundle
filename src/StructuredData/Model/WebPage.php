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
    /** @var AbstractSchemaType|null */
    protected ?AbstractSchemaType $mainEntity = null;
    protected ?string $url = null;

    public function getType(): string
    {
        return 'WebPage';
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getMainEntity(): ?AbstractSchemaType { return $this->mainEntity; }
    public function setMainEntity(?AbstractSchemaType $mainEntity): self { $this->mainEntity = $mainEntity; return $this; }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): self { $this->url = $url; return $this; }
}
