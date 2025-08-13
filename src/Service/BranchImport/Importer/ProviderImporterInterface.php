<?php

namespace Greendot\EshopBundle\Service\BranchImport\Importer;

use Greendot\EshopBundle\Dto\ProviderBranchData;

interface ProviderImporterInterface
{
    /** @return  'posta'|'balikovna'|'zasilkovna' */
    public function key(): string;

    public function downloadTo(string $filePath): bool;

    /**
     * @return iterable<ProviderBranchData>
     */
    public function fetch(): iterable;
}