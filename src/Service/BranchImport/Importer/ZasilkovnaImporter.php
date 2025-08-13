<?php

namespace Greendot\EshopBundle\Service\BranchImport\Importer;

use Throwable;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Dto\ProviderBranchData;

#[WithMonologChannel('branch_import')]
final class ZasilkovnaImporter implements ProviderImporterInterface
{
    use BranchImportTrait;

    public const SUPPORTED_COUNTRIES = ['cz', /*'sk'*/];
    public const API_URL = 'http://www.zasilkovna.cz/api/v4/41494564a70d6de6/branch.xml';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
    ) {}

    public function key(): string { return 'zasilkovna'; }

    public function downloadTo(string $filePath): bool
    {
        return $this->downloadStreamToFile(self::API_URL, $filePath);
    }

    public function fetch(): iterable
    {
        $this->logger->info('Fetching provider feed', ['provider' => $this->key(), 'url' => self::API_URL]);

        try {
            foreach ($this->streamXmlElements(self::API_URL, 'branch') as $b) {
                if (!in_array($b->country, self::SUPPORTED_COUNTRIES)) continue;

                $d = new ProviderBranchData();
                $d->provider = 'zasilkovna';
                $d->providerId = (string)$b->id;
                $d->branchTypeName = 'Packeta';
                $d->country = (string)$b->country;
                $d->zip = str_pad(preg_replace('/\D/', '', (string)$b->zip), 5, '0', STR_PAD_LEFT);
                $d->name = (string)$b->name;
                $d->street = (string)$b->street;
                $d->city = (string)$b->city;
                $d->lat = (float)$b->latitude;
                $d->lng = (float)$b->longitude;
                $d->description = (string)$b->special;
                $d->transportationName = 'Zásilkovna';
                $d->active = ((int)$b->status->statusId === 1);

                $regular = $b->openingHours->regular ?? null;
                $d->openingHours = [];
                foreach (self::DAYS_CZ as $en => $cz) {
                    $d->openingHours[$cz] = (string)($regular?->$en ?? '');
                }

                yield $d;
            }
        } catch (Throwable $e) {
            $this->logger->error('Stream read failed', ['provider' => 'zasilkovna', 'message' => $e->getMessage()]);
            return;
        }
    }
}
