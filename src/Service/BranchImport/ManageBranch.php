<?php

namespace Greendot\EshopBundle\Service\BranchImport;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Branch;
use Greendot\EshopBundle\Dto\ProviderBranchData;
use Greendot\EshopBundle\Entity\Project\BranchType;
use Greendot\EshopBundle\Repository\Project\BranchRepository;
use Greendot\EshopBundle\Service\BranchImport\Importer\PostaImporter;
use Greendot\EshopBundle\Service\BranchImport\Importer\BranchImportTrait;
use Greendot\EshopBundle\Service\BranchImport\Importer\BalikovnaImporter;
use Greendot\EshopBundle\Service\BranchImport\Importer\ZasilkovnaImporter;
use Greendot\EshopBundle\Service\BranchImport\Importer\ProviderImporterInterface;

#[WithMonologChannel('branch_import')]
final readonly class ManageBranch
{
    use BranchImportTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private BranchRepository       $branchRepository,
        private PostaImporter          $postaImporter,
        private BalikovnaImporter      $balikovnaImporter,
        private ZasilkovnaImporter     $zasilkovnaImporter,
        private LoggerInterface        $logger,
    ) {}

    public function importNapostu(): void
    {
        $this->importFrom($this->postaImporter);
    }

    public function importBalikovna(): void
    {
        $this->importFrom($this->balikovnaImporter);
    }

    public function importZasilkovna(): void
    {
        $this->importFrom($this->zasilkovnaImporter);
    }

    private function importFrom(ProviderImporterInterface $importer): void
    {
        $provider = $importer->key();
        $this->logger->info('Branch import started', ['provider' => $provider]);

        $byType = [];
        $totalFetched = 0;

        try {
            foreach ($importer->fetch() as $row) {
                $byType[$row->branchTypeName][] = $row;
                $totalFetched++;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Branch import failed while fetching', [
                'provider' => $provider,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->logger->info('Fetched provider data', [
            'provider' => $provider,
            'types' => array_keys($byType),
            'count' => $totalFetched,
        ]);

        $grandCreated = 0;
        $grandReactivated = 0;
        $grandDeactivated = 0;

        foreach ($byType as $typeName => $rows) {
            $type = $this->getOrCreateBranchType($typeName);
            $seen = [];
            $created = 0;
            $reactivated = 0;

            $this->logger->info('Processing branch type', [
                'provider' => $provider,
                'type' => $typeName,
                'rows' => \count($rows),
            ]);

            /** @var ProviderBranchData $row */
            foreach ($rows as $row) {
                $seen[] = $row->providerId;

                $branch = $this->branchRepository->findOneBy([
                    'provider_id' => $row->providerId,
                    'BranchType' => $type,
                ]);

                if ($branch) {
                    $this->touchExisting($branch);
                    $reactivated++;
                    continue;
                }

                $branch = $this->createBranch($row, $type);
                $this->em->persist($branch);
                $created++;
            }

            $this->em->flush();

            $deactivated = $this->branchRepository->deactivateMissingByType($type, array_values(array_unique($seen)));

            $grandCreated += $created;
            $grandReactivated += $reactivated;
            $grandDeactivated += $deactivated;

            $this->logger->info('Branch type synchronized', [
                'provider' => $provider,
                'type' => $typeName,
                'created' => $created,
                'reactivated' => $reactivated,
                'deactivated' => $deactivated,
            ]);
        }

        $this->em->flush();

        $this->logger->info('Branch import finished', [
            'provider' => $provider,
            'created' => $grandCreated,
            'reactivated' => $grandReactivated,
            'deactivated' => $grandDeactivated,
            'fetched' => $totalFetched,
        ]);
    }

    private function touchExisting(Branch $branch): void
    {
        $branch->setActive(1);
        $this->em->persist($branch);
    }

    private function createBranch(ProviderBranchData $d, BranchType $type): Branch
    {
        $b = (new Branch())
            ->setCountry($d->country)
            ->setActive(1)
            ->setBranchType($type)
            ->setProviderId($d->providerId)
            ->setZip($d->zip)
            ->setName($d->name)
            ->setStreet($d->street)
            ->setCity($d->city)
            ->setLat($d->lat)
            ->setLng($d->lng)
            ->setDescription($d->description)
            ->setTransportation(
                $this->transportationByName($d->transportationName),
            )
        ;
        $this->attachOpeningHours($b, $d->openingHours);

        return $b;
    }
}
