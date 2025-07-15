<?php

namespace Greendot\EshopBundle\Message;

class Export
{
    public function __construct(
        private readonly int     $objectId,
        private readonly string  $exportClass,
        private readonly int     $exportId,
        private readonly ?string $locale = null,
        private readonly ?string $currencyId = null
    ){}

    public function getObjectId(): int
    {
        return $this->objectId;
    }

    public function getExportClass(): string
    {
        return $this->exportClass;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function getExportId(): int
    {
        return $this->exportId;
    }

}