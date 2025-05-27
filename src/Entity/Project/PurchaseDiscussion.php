<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['purchase_discussion:read']]),
        new Get(normalizationContext: ['groups' => ['purchase_discussion:read']]),
        new Post(denormalizationContext: ['groups' => ['purchase_discussion:write']]),
        new Patch(denormalizationContext: ['groups' => ['purchase_discussion:write']]),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['purchase' => 'exact'])]
#[ORM\Entity]
class PurchaseDiscussion extends Message
{
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(["purchase_discussion:read", "purchase_discussion:write", 'purchase:read', 'purchase:write'])]
    private bool $isAdmin;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(["purchase_discussion:read", "purchase_discussion:write", 'purchase:read', 'purchase:write'])]
    private bool $isRead = false;

    #[ORM\ManyToOne(targetEntity: Purchase::class, inversedBy: 'purchaseDiscussions')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(["purchase_discussion:read", "purchase_discussion:write"])]
    private Purchase $purchase;

    public function getIsAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin): void
    {
        $this->isAdmin = $isAdmin;
    }


    public function getIsRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): void
    {
        $this->isRead = $isRead;
    }

    public function getPurchase(): Purchase
    {
        return $this->purchase;
    }

    public function setPurchase(Purchase $purchase): void
    {
        $this->purchase = $purchase;
    }
}
