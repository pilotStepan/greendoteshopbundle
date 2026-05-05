<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\Persistence\ManagerRegistry;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use Traversable;

class ReviewStatsStateProvider implements ProviderInterface
{
    public function __construct(
        private ReviewRepository $reviewRepository,
        private ManagerRegistry  $managerRegistry,
        #[AutowireIterator('api_platform.doctrine.orm.query_extension.collection')]
        private iterable         $collectionExtensions,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        // Create base query builder
        $em = $this->managerRegistry->getManagerForClass('Greendot\EshopBundle\Entity\Project\Review');
        $queryBuilder = $em->createQueryBuilder()
            ->select('r')
            ->from('Greendot\EshopBundle\Entity\Project\Review', 'r')
        ;

        // Apply all collection extensions (filters)
        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {
            if ($extension instanceof QueryCollectionExtensionInterface) {
                $extension->applyToCollection(
                    $queryBuilder,
                    $queryNameGenerator,
                    'Greendot\EshopBundle\Entity\Project\Review',
                    $operation,
                    $context
                );
            }
        }

        $count = (int)(clone $queryBuilder)
            ->select('COUNT(DISTINCT r.id)')
            ->getQuery()->getSingleScalarResult() ?? 0;

        // Get statistics
        $stats = $this->reviewRepository->getStats($queryBuilder);

        return new class($stats, $count) implements \IteratorAggregate, \Countable {
            public function __construct(private array $stats, private int $total) {}
            public function getIterator(): Traversable { return new \ArrayIterator($this->stats); }
            public function count(): int{ return $this->total; }
        };
    }
}