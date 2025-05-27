<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['note:read']]),
        new Get(normalizationContext: ['groups' => ['note:read']]),
        new Post(denormalizationContext: ['groups' => ['note:write']]),
        new Patch(denormalizationContext: ['groups' => ['note:write']]),
    ],
    normalizationContext: ['groups' => ['note:read']],
    denormalizationContext: ['groups' => ['note:write']],
    order: ['date_issue' => 'DESC']
)]
#[ApiFilter(SearchFilter::class, properties: ['purchase' => 'exact'])]
#[ORM\Entity]
class Note extends Message
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['purchase:read', 'purchase:write', 'note:read', 'note:write', 'event_purchase'])]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: Purchase::class, inversedBy: 'notes')]
    #[Groups(['note:read', 'note:write'])]
    private ?Purchase $purchase = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getPurchase(): ?Purchase
    {
        return $this->purchase;
    }

    public function setPurchase(?Purchase $purchase): self
    {
        $this->purchase = $purchase;

        return $this;
    }
}
