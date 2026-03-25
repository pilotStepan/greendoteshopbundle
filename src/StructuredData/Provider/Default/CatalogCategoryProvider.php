<?php

namespace Greendot\EshopBundle\StructuredData\Provider\Default;

use App\Enum\CategoryType;
use Greendot\EshopBundle\StructuredData\Model\Brand;
use Greendot\EshopBundle\StructuredData\Model\ItemList;
use Greendot\EshopBundle\StructuredData\Model\ListItem;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\StructuredData\Model\AggregateRating;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;
use Greendot\EshopBundle\StructuredData\Model\Product as ProductModel;
use Greendot\EshopBundle\StructuredData\Contract\StructuredDataProviderInterface;


class CatalogCategoryProvider implements StructuredDataProviderInterface
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

    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @param CategoryEntity $object
     */
    public function provide(mixed $object): ItemList
    {
        $products = $this->productRepository->findCategoryProducts($object, 20); // TODO: pagination?

        $elements = array_map(
            fn($p, $index) => $this->mapToListItem($p, $index + 1),
            $products,
            array_keys($products),
        );

        return (new ItemList())
            ->setName($object->getName())
            ->setItemListElement($elements)
        ;
    }

    private function mapToListItem(ProductEntity $product, int $position): ListItem
    {
        $url = $this->urlGenerator->generate('shop_product', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        return (new ListItem())
            ->setPosition($position)
            ->setItem(
                (new ProductModel())
                    ->setId(sprintf('%s#product', $url))
                    ->setUrl($url)
                    ->setName($product->getName())
                    ->setImage($this->absoluteUrl . $product->getUpload()?->getPath())
                    ->setBrand((new Brand())->setName($product->getProducer()?->getName()))
                    ->setDescription($product->getTextGeneral())
                    ->setAggregateRating($this->createRating($product)),
            )
        ;
    }

    private function createRating(ProductEntity $product): AggregateRating
    {
        return (new AggregateRating())
            ->setRatingValue($this->reviewRepository->getAvgRatingValueForProduct($product))
            ->setReviewCount($this->reviewRepository->getReviewCountForProduct($product))
        ;
    }
}
