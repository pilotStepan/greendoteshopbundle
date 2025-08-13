<?php

namespace Greendot\EshopBundle\Service\Imports\Branch;

use Greendot\EshopBundle\Entity\Project\Branch;
use Greendot\EshopBundle\Entity\Project\BranchOpeningHours;

class BranchOpeningHoursHelpers
{
    /** @param array<string,string> $openingHours e.g. ['Pondělí' => '08:00–12:00, 13:00–17:00'] */
    public static function attachOpeningHours(Branch $branch, array $openingHours): void
    {
        foreach ($openingHours as $day => $full) {
            $from = $to = '';
            if ($full !== '') {
                if (str_contains($full, ',')) {
                    [$from, $to] = self::mergeIntervals($full);
                } else {
                    [$from, $to] = self::splitInterval($full);
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

    /** @param array<string,string> $openingHours e.g. ['Pondělí' => '08:00–12:00, 13:00–17:00'] */
    public static function syncOpeningHours(Branch $branch, array $openingHours): void
    {
        $existingByDay = [];
        foreach ($branch->getBranchOpeningHours() as $h) {
            $existingByDay[(string)$h->getDay()] = $h;
        }

        foreach ($openingHours as $day => $full) {
            $from = $to = '';
            if ($full !== '') {
                if (str_contains($full, ',')) {
                    [$from, $to] = self::mergeIntervals($full);
                } else {
                    [$from, $to] = self::splitInterval($full);
                }
            }

            if (isset($existingByDay[$day])) {
                $h = $existingByDay[$day];
                unset($existingByDay[$day]);
            } else {
                $h = new BranchOpeningHours();
                $branch->addBranchOpeningHour($h);
            }

            $h->setDay($day);
            $h->setOpenedFrom($from);
            $h->setOpenedUntil($to);
            $h->setFullTime($full);
        }

        // Remove days no longer present
        if (!empty($existingByDay)) {
            foreach ($existingByDay as $obsolete) {
                $branch->removeBranchOpeningHour($obsolete);
            }
        }
    }

    /** @return array{0:string,1:string} */
    private static function splitInterval(string $range): array
    {
        [$a, $b] = array_map('trim', explode('–', $range));
        return [$a, $b];
    }

    /** @return array{0:string,1:string} */
    private static function mergeIntervals(string $csv): array
    {
        $parts = array_map('trim', explode(',', $csv));
        [$from] = self::splitInterval($parts[0]);
        [, $to] = self::splitInterval($parts[count($parts) - 1]);
        return [$from, $to];
    }
}