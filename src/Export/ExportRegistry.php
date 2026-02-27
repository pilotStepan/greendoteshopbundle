<?php

namespace Greendot\EshopBundle\Export;

use Greendot\EshopBundle\Export\Contract\ExportTypeInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class ExportRegistry
{
    /** @var ExportTypeInterface[] */
    private array $exportTypes = [];

    public function __construct(
        #[AutowireIterator('app.chunked_export')]
        iterable $exportTypes
    )
    {
        foreach ($exportTypes as $export){
            $this->exportTypes[$export::getAlias()] = $export;
        }
    }

    public function get(string $alias): ExportTypeInterface
    {
        if (!isset($this->exportTypes[$alias])){
            throw new \InvalidArgumentException("Export type $alias not found.");
        }
        return $this->exportTypes[$alias];
    }


}