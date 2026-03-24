<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org Answer model.
 * @link https://schema.org/Answer
 */
class Answer extends AbstractSchemaType
{
    protected ?string $text = null;

    public function getType(): string
    {
        return 'Answer';
    }

    public function getText(): ?string { return $this->text; }

    public function setText(?string $text): self
    {
        $this->text = $text;
        return $this;
    }
}
