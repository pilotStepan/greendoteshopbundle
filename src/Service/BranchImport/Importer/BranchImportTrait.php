<?php

namespace Greendot\EshopBundle\Service\BranchImport\Importer;

use Greendot\EshopBundle\Entity\Project\Branch;
use Greendot\EshopBundle\Entity\Project\BranchType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\BranchOpeningHours;

trait BranchImportTrait
{
    public const DAYS_CZ = [
        'monday' => 'Pondělí', 'tuesday' => 'Úterý', 'wednesday' => 'Středa',
        'thursday' => 'Čtvrtek', 'friday' => 'Pátek', 'saturday' => 'Sobota', 'sunday' => 'Neděle',
    ];

    public function loadXml(string $url): \SimpleXMLElement
    {
        $content = @file_get_contents($url);
        if ($content === false) {
            throw new \RuntimeException("Failed to download XML: {$url}");
        }
        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            throw new \RuntimeException("Invalid XML content from: {$url}");
        }
        return $xml;
    }

    public function providerIdFromCoords(string $x, string $y): string
    {
        return str_replace('.', '', $x) . str_replace('.', '', $y);
    }

    public function getOrCreateBranchType(string $name): BranchType
    {
        $repo = $this->em->getRepository(BranchType::class);
        $type = $repo->findOneBy(['name' => $name]);
        if ($type) {
            return $type;
        }
        $type = (new BranchType())->setName($name);
        $this->em->persist($type);
        return $type;
    }

    public function transportationByName(string $name): ?Transportation
    {
        return $this->em->getRepository(Transportation::class)->findOneBy(['name' => $name]);
    }

    /** @param array<string,string> $openingHours e.g. ['Pondělí' => '08:00–12:00, 13:00–17:00'] */
    public function attachOpeningHours(Branch $branch, array $openingHours): void
    {
        foreach ($openingHours as $day => $full) {
            $from = $to = '';
            if ($full !== '') {
                if (str_contains($full, ',')) {
                    [$from, $to] = $this->mergeIntervals($full);
                } else {
                    [$from, $to] = $this->splitInterval($full);
                }
            }
            $h = (new BranchOpeningHours())
                ->setDay($day)
                ->setOpenedFrom($from)
                ->setOpenedUntil($to)
                ->setFullTime($full)
            ;
            $branch->addBranchOpeningHour($h);
        }
    }

    /** @return array{0:string,1:string} */
    private function splitInterval(string $range): array
    {
        [$a, $b] = array_map('trim', explode('–', $range));
        return [$a, $b];
    }

    /** @return array{0:string,1:string} */
    private function mergeIntervals(string $csv): array
    {
        $parts = array_map('trim', explode(',', $csv));
        [$from] = $this->splitInterval($parts[0]);
        [, $to] = $this->splitInterval($parts[count($parts) - 1]);
        return [$from, $to];
    }
}
