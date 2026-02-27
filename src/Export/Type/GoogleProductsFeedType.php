<?php

namespace Greendot\EshopBundle\Export\Type;

use Doctrine\ORM\QueryBuilder;
use Greendot\EshopBundle\Export\Contract\ExportTypeInterface;
use Greendot\EshopBundle\Export\Data\Factory\GoogleProductFeedFactory;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;

class GoogleProductsFeedType implements ExportTypeInterface
{

    public function __construct(
        private readonly ProductVariantRepository $productVariantRepository,
        private readonly GoogleProductFeedFactory $googleProductFeedFactory
    ){}

    /**
     * @inheritDoc
     */
    public static function getAlias(): string
    {
        return 'google_products_feed';
    }

    /**
     * @inheritDoc
     */
    public function getTotalCount(): int
    {
        return (int) $this
            ->getQueryBase()
            ->select('COUNT(productVariant.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function getItems(int $offset, int $limit): iterable
    {
        return $this->getQueryBase()
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()->getResult();
    }

    private function getQueryBase(): QueryBuilder
    {
        return $this->productVariantRepository->createQueryBuilder('productVariant')
            ->leftJoin('productVariant.product', 'product')
            ->andWhere('product.id IS NOT NULL')
            ->andWhere('product.isActive = 1')
            ->andWhere('productVariant.isActive = 1');
    }

    /**
     * @inheritDoc
     */
    public function getFileExtension(): string
    {
        return '.xml';
    }

    public function generateStartFile(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
            '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL .
            '<channel>' . PHP_EOL .
            '<title>Google Merchant Feed</title>' . PHP_EOL;
    }

    public function generateEndFile(): string
    {
        return '</channel>' . PHP_EOL . '</rss>';
    }


    public function generateItem(mixed $item): string
    {
        $model = $this->googleProductFeedFactory->create($item);

        $xml = '<item>'. PHP_EOL;
        $xml .= sprintf('<g:id>%s</g:id>', htmlspecialchars($model->id)) . PHP_EOL;
        $xml .= sprintf('<g:title>%s</g:title>', htmlspecialchars($model->title)) . PHP_EOL;
        $xml .= sprintf('<g:description>%s</g:description>', htmlspecialchars($model->description)) . PHP_EOL;
        $xml .= sprintf('<g:link>%s</g:link>', htmlspecialchars($model->link)) . PHP_EOL;
        $xml .= sprintf('<g:image_link>%s</g:image_link>', htmlspecialchars($model->image_link)) . PHP_EOL;
        $xml .= sprintf('<g:availability>%s</g:availability>', htmlspecialchars($model->availability)) . PHP_EOL;
        $xml .= sprintf('<g:condition>%s</g:condition>', htmlspecialchars($model->condition)) . PHP_EOL;
        $xml .= sprintf('<g:price>%s</g:price>', htmlspecialchars($model->price)) . PHP_EOL;
        if ($model->salePrice){
            $xml .= sprintf('<g:sale_price>%s</g:sale_price>', htmlspecialchars($model->salePrice)) . PHP_EOL;
        }
        if ($model->productType){
            $xml .= sprintf('<g:product_type>%s</g:product_type>', htmlspecialchars($model->productType)) . PHP_EOL;
        }

        $brand = $model->brand ?? "Unknown";
        $xml .= sprintf('<g:brand>%s</g:brand>', htmlspecialchars($brand)) . PHP_EOL;

        if ($model->mpn){
            $xml .= sprintf('<g:mpn>%s</g:mpn>', htmlspecialchars($model->mpn)) . PHP_EOL;
        }

        $xml .= sprintf('<g:identifier_exists>%s</g:identifier_exists>', $model->identifier_exists ? 'yes': 'no') . PHP_EOL;
        $xml .= sprintf('<g:item_group_id>%s</g:item_group_id>', htmlspecialchars($model->itemGroupId)) . PHP_EOL;



        $xml .= '</item>'. PHP_EOL;
        return $xml;

    }
}