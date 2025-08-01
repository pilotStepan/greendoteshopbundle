<?php

namespace Greendot\EshopBundle\Dto;

class VatInfo
{
    public string $countryCode;
    public string $vatNumber;
    public string $requestDate;
    public bool $isValid;
    public bool $isForeign;
    public bool $isVatExempted;

    public ?string $name;
    public ?string $street;
    public ?string $city;
    public ?string $zip;
}