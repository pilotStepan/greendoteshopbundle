<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Algolia;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Debounces per-product Algolia writes: multiple `add()` calls for the same
 * index within the debounce window collapse into a single flush.
 */
class ProductIndexQueue
{
    private const DIRTY_KEY_PREFIX = 'algolia_product_index.dirty.';
    private const PENDING_KEY_PREFIX = 'algolia_product_index.pending.';
    private const LOCK_PREFIX = 'algolia_product_index.lock.';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LockFactory            $lockFactory,
    ) {}

    /**
     * @param int[] $ids
     */
    public function add(string $indexName, array $ids): void
    {
        if (!$ids) {
            return;
        }

        $lock = $this->lockFactory->createLock($this->lockKey($indexName));
        $lock->acquire(true);

        try {
            $item = $this->cache->getItem($this->dirtyKey($indexName));
            $existing = $item->isHit() ? $item->get() : [];
            $item->set(array_values(array_unique([...$existing, ...array_map('intval', $ids)])));
            $this->cache->save($item);
        } finally {
            $lock->release();
        }
    }

    public function claimFlush(string $indexName, int $debounceSeconds): bool
    {
        $item = $this->cache->getItem($this->pendingKey($indexName));
        if ($item->isHit()) {
            return false;
        }

        $item->set(true);
        $item->expiresAfter($debounceSeconds + 5);
        $this->cache->save($item);

        return true;
    }

    /**
     * @return int[] The ids accumulated since the last drain, empty if none.
     */
    public function drain(string $indexName): array
    {
        $lock = $this->lockFactory->createLock($this->lockKey($indexName));
        $lock->acquire(true);

        try {
            $dirtyItem = $this->cache->getItem($this->dirtyKey($indexName));
            $ids = $dirtyItem->isHit() ? $dirtyItem->get() : [];

            $this->cache->deleteItem($this->dirtyKey($indexName));
            $this->cache->deleteItem($this->pendingKey($indexName));

            return $ids;
        } finally {
            $lock->release();
        }
    }

    private function dirtyKey(string $indexName): string
    {
        return self::DIRTY_KEY_PREFIX . $this->sanitize($indexName);
    }

    private function pendingKey(string $indexName): string
    {
        return self::PENDING_KEY_PREFIX . $this->sanitize($indexName);
    }

    private function lockKey(string $indexName): string
    {
        return self::LOCK_PREFIX . $this->sanitize($indexName);
    }

    private function sanitize(string $indexName): string
    {
        return preg_replace('/[^A-Za-z0-9_.]/', '_', $indexName) ?? $indexName;
    }
}
