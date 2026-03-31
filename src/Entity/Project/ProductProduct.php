<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Greendot\EshopBundle\Enum\DiscountType;
use Greendot\EshopBundle\Entity\Project\ProductProductType;
use Greendot\EshopBundle\Repository\Project\ProductProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\StateProvider\ProductProductByParentStateProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

#[ORM\Entity(repositoryClass: ProductProductRepository::class)]
#[ApiResource(
    operations: [
        // new GetCollection(normalizationContext: ['groups'=>['product_product:read']]),
        new GetCollection(
            uriTemplate: '/productProducts/byParent/{id}',
            normalizationContext: ['groups'=>['product_product:read']],
            provider: ProductProductByParentStateProvider::class,
        ),
        // new Get(
        //     normalizationContext: ['groups'=>['product_product:read']],
        // ),
        // new Post(security: "is_granted('ROLE_ADMIN')"),
        // new Put(security: "is_granted('ROLE_ADMIN')"),
        // new Delete(security: "is_granted('ROLE_ADMIN')"),
        // new Patch(security: "is_granted('ROLE_ADMIN')"),
    ],
    denormalizationContext: ['groups' => ['product_product:write']],
)]
class ProductProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product_product:read', 'product_info:write'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'childrenProducts')]
    #[ORM\JoinColumn(nullable: false)]
    #[MaxDepth(1)]
    #[Groups(['product_product:read'])]
    private ?Product $parentProduct = null;

    #[ORM\ManyToOne(inversedBy: 'parentProducts')]
    #[Groups(['product_product:read', 'product_info:write'])]
    #[MaxDepth(1)]
    private ?Product $childrenProduct = null;

    /**
     * @var ProductProductType
     * Type for the purpose of the relation from ENUM.
     */
    #[Groups(['product_product:read'])]
    #[ORM\ManyToOne(inversedBy: 'productProducts')]
    private ?ProductProductType $productProductType;

    #[ORM\Column]
    private ?int $sequence = null;

    #[ORM\Column]
    private ?int $discount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentProduct(): ?Product
    {
        return $this->parentProduct;
    }

    public function setParentProduct(?Product $parentProduct): static
    {
        $this->parentProduct = $parentProduct;

        return $this;
    }

    public function getChildrenProduct(): ?Product
    {
        return $this->childrenProduct;
    }

    public function setChildrenProduct(?Product $childrenProduct): static
    {
        $this->childrenProduct = $childrenProduct;

        return $this;
    }

    public function getProductProductType(): ProductProductType
    {
        return $this->productProductType;
    }

    public function setProductProductType(?productProductType $productProductType): static
    {
        $this->productProductType = $productProductType;

        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): static
    {
        $this->sequence = $sequence;

        return $this;
    }

    public function getDiscount(): ?int
    {
        return $this->discount;
    }

    public function setDiscount(int $discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    
}
