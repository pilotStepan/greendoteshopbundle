<?php

namespace App\Schema\Provider;

use App\Enum\CategoryType;
use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\ItemList;
use Spatie\SchemaOrg\ListItem;
use App\Schema\ObjectNotSupported;
use App\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;


class CatalogCategoryProvider implements SchemaProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'greendot_eshop.global.absolute_url')]
        private readonly string                $absoluteUrl,
        private readonly ReviewRepository      $reviewRepository,
        private readonly ProductRepository     $productRepository,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof CategoryEntity
            && $object->getCategoryType()->getId() === CategoryType::Catalog->value;
    }

    public function provide(mixed $object): ItemList
    {
        if (!$object instanceof CategoryEntity) {
            throw new ObjectNotSupported();
        }

        $products = $this->productRepository->findCategoryProducts($object, 20); // TODO: pagination?

        $elements = array_map(
            fn($p, $index) => $this->mapToListItem($p, $index + 1),
            $products,
            array_keys($products),
        );

        return Schema::itemList()
            ->name($object->getName())
            ->itemListElement($elements)
        ;
    }

    public function getPriority(): int
    {
        return 0;
    }

    private function mapToListItem(ProductEntity $product, int $position): ListItem
    {
        $url = $this->urlGenerator->generate('shop_product', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        return Schema::listItem()
            ->position($position)
            ->item(Schema::product()
                ->identifier(sprintf('%s#product', $url))
                ->url($url)
                ->name($product->getName())
                ->image($this->absoluteUrl . $product->getUpload()?->getPath())
                ->brand(Schema::brand()
                    ->name($product->getProducer()?->getName()),
                )
                ->description($product->getTextGeneral())
                ->aggregateRating(
                    Schema::aggregateRating()
                        ->ratingValue($this->reviewRepository->getAvgRatingValueForProduct($product))
                        ->reviewCount($this->reviewRepository->getReviewCountForProduct($product)),
                ),
            )
        ;
    }
}
