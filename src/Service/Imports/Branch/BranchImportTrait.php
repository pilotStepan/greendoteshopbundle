<?php

namespace Greendot\EshopBundle\Service\Imports\Branch;

use Generator;
use XMLReader;
use SimpleXMLElement;
use RuntimeException;

trait BranchImportTrait
{
    private array $transportationIdCache = [];

    protected const DAYS_CZ = [
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

    protected function providerIdFromCoords(string $prefix, string $x, string $y, string $suffix = ''): string
    {
        $lat = number_format((float)$x, 6, '.', '');
        $lng = number_format((float)$y, 6, '.', '');
        return "{$prefix}_{$lat}_{$lng}_{$suffix}";
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
