<?php

namespace Greendot\EshopBundle\Service\BranchImport\Importer;

use Greendot\EshopBundle\Dto\ProviderBranchData;

interface ProviderImporterInterface
{
    /** @return  'posta'|'balikovna'|'zasilkovna' */
    public function key(): string;

    /**
     * @return iterable<ProviderBranchData>
     */
    public function fetch(): iterable;
}