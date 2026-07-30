<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Algolia;

/**
 * Rebuilds an Algolia product index from the database.
 *
 * Projects override the record shape / product selection by extending
 * {@see ProductIndexer} and aliasing this interface to their subclass in
 * the project's own services.yaml, e.g.:
 *
 *   Greendot\EshopBundle\Algolia\ProductIndexerInterface: '@App\Service\Algolia\MyProductIndexer'
 */
interface ProductIndexerInterface
{
    /**
     * @param string|null $indexName Overrides the configured default index name for this run.
     */
    public function reindex(?string $indexName = null): ReindexResult;

    public function countProducts(): int;
}
