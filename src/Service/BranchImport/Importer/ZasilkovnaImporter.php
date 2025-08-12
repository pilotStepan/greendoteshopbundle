<?php

namespace Greendot\EshopBundle\Service\BranchImport\Importer;

use SimpleXMLElement;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Dto\ProviderBranchData;

#[WithMonologChannel('branch_import')]
final class ZasilkovnaImporter implements ProviderImporterInterface
{
    use BranchImportTrait;

    public const API_URL = 'http://www.zasilkovna.cz/api/v4/41494564a70d6de6/branch.xml';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
    ) {}

    public function key(): string { return 'zasilkovna'; }

    public function fetch(): iterable
    {
        $this->logger->info('Fetching provider feed', ['provider' => $this->key(), 'url' => self::API_URL]);

        try {
            $xml = $this->loadXml(self::API_URL);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch provider feed', [
                'provider' => $this->key(),
                'url' => self::API_URL,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            return;
        }

        foreach ($xml->branches->branch as $b) {
            if ((int)$b->status->statusId !== 1) {
                continue;
            }

            $d = new ProviderBranchData();
            $d->provider = 'zasilkovna';
            $d->providerId = (string)$b->id;
            $d->branchTypeName = 'Packeta';
            $d->country = 'cz';
            $d->zip = (string)$b->zip;
            $d->name = (string)$b->name;
            $d->street = (string)$b->street;
            $d->city = (string)$b->city;
            $d->lat = (float)$b->latitude;
            $d->lng = (float)$b->longitude;
            $d->description = (string)$b->special;
            $d->transportationName = 'Zásilkovna';
            $d->openingHours = $this->hours($b->openingHours->regular ?? null);

            yield $d;
        }
    }

    /** @return array<string,string> */
    private function hours(?SimpleXMLElement $regular): array
    {
        $out = [];
        foreach (self::DAYS_CZ as $en => $cz) {
            $out[$cz] = (string)($regular?->$en ?? '');
        }
        return $out;
    }
}
