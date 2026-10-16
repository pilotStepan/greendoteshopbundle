<?php

namespace Greendot\EshopBundle\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Greendot\EshopBundle\Repository\Project\BranchRepository;
use Greendot\EshopBundle\Repository\Project\BranchTypeRepository;

/**
 * Provides the whole branch list of a transportation group as one compact, cached JSON document
 * for client-side map clustering. The payload is cart-independent (no prices).
 */
readonly class BranchMapProvider
{
    private const VERSION_KEY = 'branch_map_version';
    private const TTL = 3600;

    public function __construct(
        private CacheInterface       $cache,
        private BranchRepository     $branchRepository,
        private BranchTypeRepository $branchTypeRepository,
    ) {}

    /**
     * @return array{json: string, etag: string}
     */
    public function get(int $groupId, string $country): array
    {
        $country = strtolower($country);
        $version = $this->cache->get(self::VERSION_KEY, static fn() => bin2hex(random_bytes(6)));
        $key = sprintf('branch_map_%s_%d_%s', $version, $groupId, preg_replace('/[^a-z]/', '', $country));

        return $this->cache->get($key, function (ItemInterface $item) use ($groupId, $country) {
            $item->expiresAfter(self::TTL);

            $json = json_encode($this->build($groupId, $country), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return ['json' => $json, 'etag' => md5($json)];
        });
    }

    /** Makes all cached documents stale, call after branches change in bulk (imports). */
    public function invalidate(): void
    {
        $this->cache->delete(self::VERSION_KEY);
    }

    private function build(int $groupId, string $country): array
    {
        $rows = $this->branchRepository->findCompactForMap($groupId, $country);

        $types = [];
        $typeIds = array_keys(array_column($rows, 3, 3));
        foreach ($typeIds ? $this->branchTypeRepository->findBy(['id' => $typeIds], ['id' => 'ASC']) : [] as $type) {
            $types[] = ['id' => $type->getId(), 'name' => $type->getName(), 'icon' => $type->getIcon()];
        }

        return [
            'fields' => ['id', 'lat', 'lng', 'branchTypeId', 'transportationId', 'name', 'street', 'city', 'zip'],
            'types' => $types,
            'rows' => $rows,
        ];
    }
}
