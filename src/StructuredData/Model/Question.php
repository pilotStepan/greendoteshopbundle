<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org Question model.
 * @link https://schema.org/Question
 */
class Question extends AbstractSchemaType
{
    protected ?string $name = null;
    /** @var Answer|null */
    protected ?Answer $acceptedAnswer = null;

    public function getType(): string
    {
        return 'Question';
    }

    public function getName(): ?string { return $this->name; }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getAcceptedAnswer(): ?Answer { return $this->acceptedAnswer; }

    public function setAcceptedAnswer(?Answer $acceptedAnswer): self
    {
        $this->acceptedAnswer = $acceptedAnswer;
        return $this;
    }
}
