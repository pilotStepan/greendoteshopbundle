<?php

namespace Greendot\EshopBundle\Service\BranchImport\Importer;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Dto\ProviderBranchData;

#[WithMonologChannel('branch_import')]
final class PostaImporter implements ProviderImporterInterface
{
    use BranchImportTrait;

    public const API_URL = 'http://napostu.ceskaposta.cz/vystupy/napostu_1.xml';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
    ) {}

    public function key(): string { return 'posta'; }

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

        foreach ($xml->row as $row) {
            $d = new ProviderBranchData();
            $d->provider = 'posta';
            $d->providerId = $this->providerIdFromCoords((string)$row->SOUR_X_WGS84, (string)$row->SOUR_Y_WGS84);
            $d->branchTypeName = 'Pošta';
            $d->country = 'cz';
            $d->zip = (string)$row->PSC;
            $d->name = (string)$row->NAZ_PROV;
            $d->street = (string)$row->ADRESA;
            $d->city = (string)$row->OKRES;
            $d->lat = (float)$row->SOUR_X_WGS84;
            $d->lng = (float)$row->SOUR_Y_WGS84;
            $d->description = '';
            $d->transportationName = 'Balík na poštu';
            $d->openingHours = $this->hours($row->OTV_DOBA->den ?? []);

            yield $d;
        }
    }

    /** @return array<string,string> */
    private function hours(\SimpleXMLElement $days): array
    {
        $out = [];
        foreach ($days as $den) {
            $name = (string)$den['name'];
            $from = (string)($den->od_do?->od ?? '');
            $to = (string)($den->od_do?->do ?? '');
            $out[$name] = ($from && $to) ? "{$from}–{$to}" : '';
        }
        return $out;
    }
}
