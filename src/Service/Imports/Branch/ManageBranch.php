<?php

namespace Greendot\EshopBundle\Service\Imports\Branch;

use Throwable;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Branch;
use Greendot\EshopBundle\Dto\ProviderBranchData;
use Greendot\EshopBundle\Entity\Project\BranchType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Repository\Project\BranchRepository;
use Doctrine\Bundle\DoctrineBundle\Middleware\DebugMiddleware;

#[WithMonologChannel('branch_import')]
final class ManageBranch
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private EntityManagerInterface  $em,
        private BranchRepository        $branchRepository,
        private CzechPostBranchImporter $czechPostBranchImporter,
        private PacketaBranchImporter   $packetaBranchImporter,
        private LoggerInterface         $logger,
    ) {}

    public function importCzechPost(): array { return $this->importFrom($this->czechPostBranchImporter); }

    public function importPacketa(): array { return $this->importFrom($this->packetaBranchImporter); }

    /**
     * @return array{provider: string, processed: int, created: int, updated: int}
     * @throws Throwable
     */
    private function importFrom(ProviderImporterInterface $importer): array
    {
        $provider = $importer->key();
        $this->logger->info('Branch import started', ['provider' => $provider]);

        // temporarily remove DBAL debug middleware (for performance)
        $prevMiddlewares = $this->disableDbalDebugMiddleware();

        try {
            $stats = $this->em->getConnection()->transactional(function () use ($importer, $provider) {
                // cache typeName => typeId
                $typeIdCache = [];
                $seenByType = [];
                $stats = [
                    'provider' => $provider,
                    'processed' => 0,
                    'created' => 0,
                    'updated' => 0,
                ];

                /** @var ProviderBranchData $row */
                foreach ($importer->fetch() as $row) {
                    ++$stats['processed'];

                    if (!isset($typeIdCache[$row->branchTypeName])) {
                        $type = $this->getOrCreateBranchType($row->branchTypeName);
                        if ($type->getId() === null) {
                            $this->em->persist($type);
                            $this->em->flush();
                        }
                        $typeIdCache[$row->branchTypeName] = (int)$type->getId();
                    }

                    $typeId = $typeIdCache[$row->branchTypeName];
                    $typeRef = $this->em->getReference(BranchType::class, $typeId);

                    $seenByType[$row->branchTypeName][] = $row->providerId;

                    $branch = $this->branchRepository->findOneByProviderIdAndTypeId($row->providerId, $typeId);

                    if ($branch) {
                        $this->updateBranch($branch, $row);
                        ++$stats['updated']; // count as updated even if unchanged
                    } else {
                        $branch = $this->createBranch($row, $typeRef);
                        $this->em->persist($branch);
                        ++$stats['created'];
                    }

                    if (($stats['processed'] % self::BATCH_SIZE) === 0) {
                        $this->em->flush();
                        $this->em->clear();
                        $this->logger->debug('Batch flushed', $stats);
                    }
                }

                $this->em->flush();
                $this->em->clear();

                foreach ($seenByType as $typeName => $ids) {
                    $typeId = $typeIdCache[$typeName];
                    $typeRef = $this->em->getReference(BranchType::class, $typeId);

                    $count = $this->branchRepository->deactivateMissingByType(
                        $typeRef,
                        array_values(array_unique($ids)),
                    );

                    $this->logger->info('Deactivated missing branches', [
                        'provider' => $provider, 'type' => $typeName, 'count' => $count,
                    ]);
                }

                return $stats;
            });

            $this->logger->info('Branch import finished', $stats);

            return $stats;
        } catch (Throwable $e) {
            $this->logger->error('Branch import failed', [
                'provider' => $provider,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            $this->restoreDbalMiddlewares($prevMiddlewares);
        }
    }

    private function disableDbalDebugMiddleware(): ?array
    {
        $config = $this->em->getConnection()->getConfiguration();
        $prev = $config->getMiddlewares();
        $filtered = array_filter($prev, function ($mw) {
            return !(
                (is_object($mw) && is_a($mw, Middleware::class, true)) ||
                (is_object($mw) && class_exists(DebugMiddleware::class)
                    && is_a($mw, DebugMiddleware::class, true))
            );
        });
        $config->setMiddlewares($filtered);
        return $prev;
    }

    private function restoreDbalMiddlewares(?array $prev): void
    {
        if ($prev === null) return;
        $config = $this->em->getConnection()->getConfiguration();
        $config->setMiddlewares($prev);
    }

    private function getOrCreateBranchType(string $name): BranchType
    {
        $repo = $this->em->getRepository(BranchType::class);
        $type = $repo->findOneBy(['name' => $name]);

        if ($type) return $type;

        return (new BranchType())->setName($name);
    }

    private function createBranch(ProviderBranchData $d, BranchType $type): Branch
    {
        $b = (new Branch())
            ->setCountry($d->country)
            ->setActive($d->active)
            ->setBranchType($type)
            ->setProviderId($d->providerId)
            ->setZip($d->zip)
            ->setName($d->name)
            ->setStreet($d->street)
            ->setCity($d->city)
            ->setLat($d->lat)
            ->setLng($d->lng)
            ->setDescription($d->description)
            ->setTransportation($this->transportationByName($d->transportationName))
        ;

        BranchOpeningHoursHelpers::attachOpeningHours($b, $d->openingHours);

        return $b;
    }

    private function updateBranch(Branch $branch, ProviderBranchData $d): void
    {
        $branch
            ->setActive($d->active)
            ->setZip($d->zip)
            ->setName($d->name)
            ->setStreet($d->street)
            ->setCity($d->city)
            ->setLat($d->lat)
            ->setLng($d->lng)
            ->setDescription($d->description)
            ->setTransportation($this->transportationByName($d->transportationName))
        ;

        BranchOpeningHoursHelpers::syncOpeningHours($branch, $d->openingHours);
    }

    private function transportationByName(string $name): ?Transportation
    {
        $name = trim($name);
        if ($name === '') return null;

        if (isset($this->transportationIdCache[$name])) {
            return $this->em->getReference(Transportation::class, $this->transportationIdCache[$name]);
        }

        // Fetch only the scalar ID — no entity hydration, no listeners
        $id = $this->em->getConnection()->fetchOne(
            'SELECT id FROM transportation WHERE name = :name LIMIT 1',
            ['name' => $name],
        );

        if ($id === false) return null;

        $id = (int)$id;
        $this->transportationIdCache[$name] = $id;

        return $this->em->getReference(Transportation::class, $id);
    }
}
