<?php

namespace Greendot\EshopBundle\Service\Imports\Branch;

use Greendot\EshopBundle\Dto\ProviderBranchData;

interface ProviderImporterInterface
{
    /** @return  'czechpost'|'packeta' */
    public function key(): string;

    public function downloadTo(string $filePath): bool;

    /**
     * @return iterable<ProviderBranchData>
     */
    public function fetch(): iterable;
}