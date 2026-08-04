<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Algolia;

final readonly class ReindexResult
{
    public function __construct(
        public string $indexName,
        public int    $indexed,
        public float  $durationSeconds,
        public array  $settingsApplied,
    ) {
    }

    public function toArray(): array
    {
        return [
            'indexName' => $this->indexName,
            'indexed' => $this->indexed,
            'durationSeconds' => $this->durationSeconds,
            'settingsApplied' => $this->settingsApplied,
        ];
    }
}
