<?php

namespace Greendot\EshopBundle\Export\Contract;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.chunked_export')]
interface ExportTypeInterface
{
    /** The unique identifier (e.g., 'google_products_feed') */
    public static function getAlias(): string;

    /** Returns the total number of items to export so the bundle can calculate chunks */
    public function getTotalCount(): int;

    /** Returns the actual items for a specific chunk */
    public function getItems(int $offset, int $limit): iterable;

    public function generateStartFile(): string;

    public function generateItem(mixed $item): string;

    public function generateEndFile(): string;

    /** e.g., '.xml' or '.csv' */
    public function getFileExtension(): string;
}