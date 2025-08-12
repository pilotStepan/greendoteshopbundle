<?php

namespace Greendot\EshopBundle\Service\BranchImport\Importer;

use Throwable;
use SimpleXMLElement;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Dto\ProviderBranchData;
use Greendot\EshopBundle\Service\BranchImport\BranchImportTrait;

#[WithMonologChannel('branch_import')]
final class BalikovnaImporter implements ProviderImporterInterface
{
    use BranchImportTrait;

    public const API_URL = 'http://napostu.ceskaposta.cz/vystupy/balikovny.xml';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
    ) {}

    public function key(): string { return 'balikovna'; }

    public function downloadTo(string $filePath): bool
    {
        return $this->downloadStreamToFile(self::API_URL, $filePath);
    }

    public function fetch(): iterable
    {
        $this->logger->info('Streaming provider feed', ['provider' => $this->key(), 'url' => self::API_URL]);

        try {
            foreach ($this->streamXmlElements(self::API_URL, 'row') as $row) {
                $d = new ProviderBranchData();
                $d->provider = 'balikovna';
                $d->providerId = $this->providerIdFromCoords((string)$row->SOUR_X_WGS84, (string)$row->SOUR_Y_WGS84);
                $d->branchTypeName = str_contains((string)$row->NAZEV, 'AlzaBox') ? 'AlzaBox' : ucfirst((string)$row->TYP);
                $d->country = 'cz';
                $d->zip = (string)$row->PSC;
                $d->name = (string)$row->NAZEV;
                $d->street = (string)$row->ADRESA;
                $d->city = (string)$row->OKRES;
                $d->lat = (float)$row->SOUR_X_WGS84;
                $d->lng = (float)$row->SOUR_Y_WGS84;
                $d->description = '';
                $d->transportationName = 'Balíkovna';
                $d->active = true; // feed only contains active branches
                $d->openingHours = $this->extractOpeningHours($row->OTEV_DOBY ?? null);

                yield $d;
            }
        } catch (Throwable $e) {
            $this->logger->error('Stream read failed', ['provider' => 'balikovna', 'message' => $e->getMessage()]);
            return;
        }
    }

    /** @return array<string,string> day => "08:00–12:00, 13:00–17:00" */
    private function extractOpeningHours(?SimpleXMLElement $otevDoby): array
    {
        $out = [];
        if (!$otevDoby) return $out;

        foreach ($otevDoby->den as $den) {
            $dayName = (string)$den['name'];
            $segments = [];

            foreach ($den->od_do as $interval) {
                $from = trim((string)$interval->od);
                $to = trim((string)$interval->do);
                if ($from !== '' && $to !== '') {
                    $segments[] = $from . '–' . $to;
                }
            }

            $out[$dayName] = $segments ? implode(', ', $segments) : '';
        }

        return $out;
    }
}
