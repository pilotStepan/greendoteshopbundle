<?php

namespace Greendot\EshopBundle\Service\BranchImport;

use Generator;
use XMLReader;
use SimpleXMLElement;
use RuntimeException;
use Greendot\EshopBundle\Entity\Project\Branch;
use Greendot\EshopBundle\Entity\Project\BranchType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\BranchOpeningHours;
use function dirname;
use function basename;

trait BranchImportTrait
{
    private array $transportationIdCache = [];

    public const DAYS_CZ = [
        'monday' => 'Pondělí', 'tuesday' => 'Úterý', 'wednesday' => 'Středa',
        'thursday' => 'Čtvrtek', 'friday' => 'Pátek', 'saturday' => 'Sobota', 'sunday' => 'Neděle',
    ];

    /**
     * Stream XML and yield only elements with the given name (no full-doc buffering).
     * @return Generator<SimpleXMLElement>
     */
    protected function streamXmlElements(string $url, string $elementName): Generator
    {
        $reader = new XMLReader();
        if (!$reader->open($url, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new RuntimeException("Failed to open XML: {$url}");
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== $elementName) {
                    continue;
                }

                $nodeXml = $reader->readOuterXML();
                if (!$nodeXml) continue;

                $node = simplexml_load_string($nodeXml, options: LIBXML_NOCDATA);
                if ($node instanceof SimpleXMLElement) {
                    yield $node;
                }

                $reader->next($elementName);
            }
        } finally {
            $reader->close();
        }
    }

    public function providerIdFromCoords(string $x, string $y): string
    {
        // FIXME: coordinates in balikovna are not unique, can't import
        $lat = number_format((float)$x, 6, '.', '');
        $lng = number_format((float)$y, 6, '.', '');
        return substr(hash('sha1', "$lat|$lng"), 0, 16);
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
        $this->em->flush();
        return $type;
    }

    public function transportationByName(string $name): ?Transportation
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

    /** @param array<string,string> $openingHours e.g. ['Pondělí' => '08:00–12:00, 13:00–17:00'] */
    public function syncOpeningHours(Branch $branch, array $openingHours): void
    {
        $existingByDay = [];
        foreach ($branch->getBranchOpeningHours() as $h) {
            $existingByDay[(string)$h->getDay()] = $h;
        }

        foreach ($openingHours as $day => $full) {
            $from = $to = '';
            if ($full !== '') {
                if (str_contains($full, ',')) {
                    [$from, $to] = $this->mergeIntervals($full);
                } else {
                    [$from, $to] = $this->splitInterval($full);
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

    protected function downloadStreamToFile(
        string $url,
        string $filePath,
        array  $contextOptions = [],
    ): bool
    {
        $dir = dirname($filePath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->logger->error('Failed to create target directory', ['dir' => $dir]);
            return false;
        }

        $tmp = $dir . '/.' . basename($filePath) . '.part';

        $ctx = stream_context_create($contextOptions + [
                'http' => ['timeout' => 30, 'user_agent' => 'BranchImporter/1.0 (+cli)'],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);

        $in = @fopen($url, 'rb', false, $ctx);
        if ($in === false) {
            $this->logger->error('Failed to open source XML', ['url' => $url, 'last_error' => error_get_last()]);
            return false;
        }

        $status = 200;
        $meta = stream_get_meta_data($in);
        if (isset($meta['wrapper_data']) && is_array($meta['wrapper_data'])) {
            foreach ($meta['wrapper_data'] as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $h, $m)) {
                    $status = (int)$m[1];
                    break;
                }
            }
        }
        if ($status >= 400) {
            fclose($in);
            $this->logger->error('HTTP error', ['status' => $status]);
            return false;
        }

        $out = @fopen($tmp, 'wb');
        if ($out === false) {
            fclose($in);
            $this->logger->error('Failed to open temp file', ['file' => $tmp]);
            return false;
        }

        @flock($out, LOCK_EX);
        $bytes = stream_copy_to_stream($in, $out);
        fflush($out);
        @flock($out, LOCK_UN);
        fclose($out);
        fclose($in);

        if ($bytes === false || $bytes === 0) {
            @unlink($tmp);
            $this->logger->error('Stream copy failed');
            return false;
        }

        if (!@rename($tmp, $filePath)) {
            @unlink($tmp);
            $this->logger->error('Atomic rename failed', ['tmp' => $tmp, 'target' => $filePath]);
            return false;
        }

        clearstatcache(true, $filePath);
        $exists = is_file($filePath);
        $size = $exists ? @filesize($filePath) : null;
        $realPath = @realpath($filePath);
        $cwd = getcwd();

        $this->logger->info('File saved', [
            'file' => $filePath,
            'realpath' => $realPath,
            'exists' => $exists,
            'bytes' => $bytes,
            'filesize' => $size,
            'status' => $status,
            'cwd' => $cwd,
            'php_os' => PHP_OS_FAMILY,
        ]);

        return $exists;
    }
}
