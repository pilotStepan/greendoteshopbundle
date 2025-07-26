<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\Persistence\ManagerRegistry;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;

class ReviewStatsStateProvider implements ProviderInterface
{
    public function __construct(
        private ReviewRepository $reviewRepository,
        private ManagerRegistry  $managerRegistry,
        #[Autowire('api_platform.doctrine.orm.query_extension.collection')]
        private iterable         $collectionExtensions,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
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

        // Get statistics
        $stats = $this->reviewRepository->getStats($queryBuilder);
        return [
            'distribution' => $stats['distribution'],
            'avgRating' => $stats['avgRating'],
        ];
    }
}