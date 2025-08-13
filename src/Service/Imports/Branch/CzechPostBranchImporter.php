<?php

namespace Greendot\EshopBundle\Service\Imports\Branch;

use Throwable;
use SimpleXMLElement;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Dto\ProviderBranchData;

#[WithMonologChannel('branch_import')]
final class CzechPostBranchImporter implements ProviderImporterInterface
{
    use BranchImportTrait;

    private const PROVIDER_KEY = 'czechpost';
    private const API_URL = 'http://napostu.ceskaposta.cz/vystupy/balikovny.xml';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
    ) {}

    public function key(): string { return self::PROVIDER_KEY; }

    public function downloadTo(string $filePath): bool
    {
        return $this->downloadStreamToFile(self::API_URL, $filePath);
    }

    public function fetch(): iterable
    {
        $this->logger->info('Streaming provider feed', ['provider' => $this->key(), 'url' => self::API_URL]);

        try {
            foreach ($this->streamXmlElements(self::API_URL, 'row') as $row) {
                if ((string)$row->TYP === 'depo') continue;

                $d = new ProviderBranchData();
                $d->provider = self::PROVIDER_KEY;
                $d->providerId = self::PROVIDER_KEY . '_' . $row->PSC; // PSC is a unique identifier
                $d->branchTypeName = $this->resolveBranchTypeName($row);
                $d->country = 'cz';
                $d->zip = $this->extractZip((string)$row->ADRESA);
                $d->name = (string)$row->NAZEV;
                $d->street = (string)$row->ADRESA;
                $d->city = (string)$row->OBEC;
                $d->lat = (float)$row->SOUR_X_WGS84;
                $d->lng = (float)$row->SOUR_Y_WGS84;
                $d->description = (string)$row->POPIS;
                $d->transportationName = $this->resolveTransportationName($row);
                $d->active = true; // feed only contains active branches
                $d->openingHours = $this->extractOpeningHours($row->OTEV_DOBY ?? null);

                yield $d;
            }
        } catch (Throwable $e) {
            $this->logger->error('Stream read failed', ['provider' => self::PROVIDER_KEY, 'message' => $e->getMessage()]);
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

    private function resolveBranchTypeName(SimpleXMLElement $row): string
    {
        if (str_contains((string)$row->NAZEV, 'AlzaBox')) {
            return 'AlzaBox';
        } else {
            return ucfirst((string)$row->TYP);
        }
    }

    private function extractZip(string $address): string
    {
        if (preg_match('/\b(?:PSČ[:\s]*)?(\d{3})\s?(\d{2})\b/u', $address, $m)) {
            return "{$m[1]}{$m[2]}";
        } else {
            return '';
        }
    }

    private function resolveTransportationName(SimpleXMLElement $row): string
    {
        if ((string)$row->TYP === 'pošta') {
            return 'Balík na poštu';
        } else {
            return 'Balíkovna';
        }
    }
}
