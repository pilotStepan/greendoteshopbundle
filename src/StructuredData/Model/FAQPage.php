<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org FAQPage model.
 * @link https://schema.org/FAQPage
 */
class FAQPage extends AbstractSchemaType
{
    /** @var Question[]|null */
    protected ?array $mainEntity = null;

    public function getType(): string
    {
        return 'FAQPage';
    }

    /**
     * @return Question[]|null
     */
    public function getMainEntity(): ?array { return $this->mainEntity; }

    /**
     * @param Question[]|null $mainEntity
     * @return $this
     */
    public function setMainEntity(?array $mainEntity): self
    {
        $this->mainEntity = $mainEntity;
        return $this;
    }
}
