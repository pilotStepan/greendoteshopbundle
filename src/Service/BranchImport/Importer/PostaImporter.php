<?php

namespace Greendot\EshopBundle\Service\BranchImport\Importer;

use Throwable;
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
                $d->provider = 'posta';
                $d->providerId = $this->providerIdFromCoords('p', (string)$row->SOUR_X_WGS84, (string)$row->SOUR_Y_WGS84);
                $d->branchTypeName = 'Pošta';
                $d->country = 'cz';
                $d->zip = str_pad(preg_replace('/\D/', '', (string)$row->PSC), 5, '0', STR_PAD_LEFT);
                $d->name = (string)$row->NAZ_PROV;
                $d->street = (string)$row->ADRESA;
                $d->city = (string)$row->OKRES;
                $d->lat = (float)$row->SOUR_X_WGS84;
                $d->lng = (float)$row->SOUR_Y_WGS84;
                $d->description = '';
                $d->transportationName = 'Balík na poštu';

                $d->openingHours = [];
                foreach (($row->OTV_DOBA->den ?? []) as $den) {
                    $name = (string)$den['name'];
                    $from = (string)($den->od_do?->od ?? '');
                    $to = (string)($den->od_do?->do ?? '');
                    $d->openingHours[$name] = ($from && $to) ? "{$from}–{$to}" : '';
                }

                yield $d;
            }
        } catch (Throwable $e) {
            $this->logger->error('Stream read failed', ['provider' => 'posta', 'message' => $e->getMessage()]);
            return;
        }
    }
}
