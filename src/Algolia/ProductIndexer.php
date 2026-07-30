<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Algolia;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Algolia\AlgoliaSearch\Api\SearchClient;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * Default Algolia product reindexer.
 */
class ProductIndexer implements ProductIndexerInterface
{
    /** @var string[] Translatable fields hydrated into `{field}_{locale}` keys for every non-primary locale. */
    protected const TRANSLATABLE_FIELDS = ['name', 'description', 'slug'];

    public function __construct(
        protected readonly SearchClient           $algolia,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ContainerBagInterface  $parameterBag,
    ) {}

    public function reindex(?string $indexName = null): ReindexResult
    {
        $indexName = $indexName ?? $this->getDefaultIndexName();
        $batchSize = $this->getBatchSize();
        $start = microtime(true);

        $this->algolia->clearObjects($indexName);

        $indexed = 0;
        $batch = [];

        foreach ($this->productGenerator() as $row) {
            $batch[] = $this->hydrateRecord($row);

            if (\count($batch) >= $batchSize) {
                $this->flush($indexName, $batch);
                $indexed += \count($batch);
                $batch = [];
            }
        }

        if ($batch) {
            $this->flush($indexName, $batch);
            $indexed += \count($batch);
        }

        $settings = $this->buildIndexSettings();
        if ($settings) {
            $this->algolia->setSettings($indexName, $settings);
        }

        return new ReindexResult(
            indexName: $indexName,
            indexed: $indexed,
            durationSeconds: microtime(true) - $start,
            settingsApplied: $settings,
        );
    }

    public function countProducts(): int
    {
        [$where, $params] = $this->getProductFilters();

        return (int)$this->connection()->fetchOne(
            "SELECT COUNT(*) FROM product p WHERE {$where}",
            $params,
        );
    }

    /**
     * Algolia index to write to when no explicit index name is given.
     */
    protected function getDefaultIndexName(): string
    {
        return 'prod_products';
    }

    protected function getLocales(): array
    {
        return $this->parameterBag->has('app.available.locales')
            ? $this->parameterBag->get('app.available.locales')
            : ['cs'];
    }

    protected function getBatchSize(): int
    {
        return 500;
    }

    private function flush(string $indexName, array $batch): void
    {
        $this->algolia->saveObjects($indexName, $batch);
        gc_collect_cycles();
    }

    /**
     * @return \Generator<array<string, mixed>>
     */
    protected function productGenerator(): \Generator
    {
        $conn = $this->connection();
        [$where, $params] = $this->getProductFilters();
        $offset = 0;
        $pageSize = 500;

        do {
            $sql = <<<SQL
                SELECT
                    p.id,
                    p.external_id,
                    p.name,
                    p.description,
                    p.slug,
                    COALESCE(p.meta_description, '') AS metaDescription,
                    COALESCE(u.path, '')             AS uploadPath
                FROM product p
                LEFT JOIN upload u ON u.id = p.upload_id
                WHERE {$where}
                ORDER BY p.id
                LIMIT :limit OFFSET :offset
                SQL;

            $rows = $conn->executeQuery(
                $sql,
                $params + ['limit' => $pageSize, 'offset' => $offset],
                ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
            )->fetchAllAssociative();

            if (!$rows) {
                break;
            }

            $translations = $this->preloadTranslations(array_column($rows, 'id'));

            foreach ($rows as $row) {
                $row['_translations'] = $translations[(int)$row['id']] ?? [];
                yield $row;
            }

            $offset += $pageSize;
        } while (true);
    }

    protected function getProductFilters(): array
    {
        return ['p.is_active = :active', ['active' => 1]];
    }

    protected function hydrateRecord(array $row): array
    {
        $id = (int)($row['id'] ?? 0);
        $translations = $row['_translations'] ?? [];

        $record = [
            'objectID' => $id,
            'externalID' => $row['external_id'] ?? '',
            'uploadPath' => (string)($row['uploadPath'] ?? ''),
            'metaDescription' => (string)($row['metaDescription'] ?? ''),
        ];

        foreach (static::TRANSLATABLE_FIELDS as $field) {
            $baseValue = (string)($row[$field] ?? '');
            $record[$field] = $baseValue;

            foreach ($this->getLocales() as $locale) {
                $record[sprintf('%s_%s', $field, $locale)] = $translations[$field][$locale] ?? $baseValue;
            }
        }

        return $record;
    }

    /**
     * Preloads translations for a batch of product ids in a single query, keyed
     * [productId][field][locale] => value, to avoid N+1 lookups.
     *
     * @param int[] $productIds
     * @return array<int, array<string, array<string, string>>>
     */
    protected function preloadTranslations(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        $conn = $this->connection();
        $result = $conn->executeQuery(
            <<<SQL
                SELECT foreign_key, field, locale, content
                FROM ext_translations
                WHERE object_class = :class
                  AND foreign_key IN (:ids)
                SQL,
            ['class' => 'Greendot\\EshopBundle\\Entity\\Project\\Product', 'ids' => $productIds],
            ['ids' => Connection::PARAM_INT_ARRAY],
        );

        $translations = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $translations[(int)$row['foreign_key']][$row['field']][$row['locale']] = $row['content'];
        }

        return $translations;
    }

    /**
     * @return array<string, mixed> Algolia index settings, or [] to leave settings untouched.
     */
    protected function buildIndexSettings(): array
    {
        $locales = $this->getLocales();
        $searchableAttributes = [];
        foreach (static::TRANSLATABLE_FIELDS as $field) {
            $searchableAttributes[] = $field;
            foreach ($locales as $locale) {
                $searchableAttributes[] = sprintf('%s_%s', $field, $locale);
            }
        }

        return [
            'searchableAttributes' => $searchableAttributes,
            'indexLanguages' => $locales,
            'queryLanguages' => $locales,
        ];
    }

    protected function connection(): Connection
    {
        return $this->entityManager->getConnection();
    }
}
