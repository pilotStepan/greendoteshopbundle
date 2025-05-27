<?php

namespace Greendot\EshopBundle\Entity\Project;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\Repository\Project\MessageRepository;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Base message entity stored in "message" table (single table inheritance).
 * This entity is abstract: you normally won't create it directly, but via subclasses.
 */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: "message")]
#[ORM\InheritanceType("SINGLE_TABLE")]
#[ORM\DiscriminatorColumn(name: "message_type", type: "string")]
#[DiscriminatorMap(typeProperty: "message_type", mapping: [
    "purchasediscussion" => PurchaseDiscussion::class,
    "note" => Note::class,
])]
abstract class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(["message:read", "purchase_discussion:read", "review:read", "purchase:read", "product:read"])]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(["message:read", "message:write", "purchase_discussion:read", "purchase_discussion:write", "review:read", "review:write", "purchase:read", "purchase:write", "product:read", "product:write"])]
    private string $content;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(["message:read", "purchase_discussion:read", "review:read", "purchase:read", "product:read"])]
    private \DateTimeImmutable $createdAt;


    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getMessageType(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }
}
