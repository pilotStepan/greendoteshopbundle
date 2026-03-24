<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org ProductGroup model.
 * @link https://schema.org/ProductGroup
 */
class ProductGroup extends Product
{
    /** @var string[]|null */
    protected ?array $variesBy = null;
    /** @var Product[]|null */
    protected ?array $hasVariant = null;

    public function getType(): string
    {
        return 'ProductGroup';
    }

    /**
     * @return string[]|null
     */
    public function getVariesBy(): ?array { return $this->variesBy; }

    /**
     * @param string[]|null $variesBy
     * @return $this
     */
    public function setVariesBy(?array $variesBy): self
    {
        $this->variesBy = $variesBy;
        return $this;
    }

    /**
     * @return Product[]|null
     */
    public function getHasVariant(): ?array { return $this->hasVariant; }

    /**
     * @param Product[]|null $hasVariant
     * @return $this
     */
    public function setHasVariant(?array $hasVariant): self
    {
        $this->hasVariant = $hasVariant;
        return $this;
    }
}
