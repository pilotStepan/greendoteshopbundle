<?php

namespace Greendot\EshopBundle\Dto;

final class ProviderBranchData
{
    /** @var 'posta'|'balikovna'|'zasilkovna' $provider */
    public string $provider;
    public string $providerId;

    /** @var 'Pošta'|'Balíkovna'|'Packeta'|'AlzaBox' etc. */
    public string $branchTypeName;
    /** @var 'cz'|'sk' */
    public string $country;
    public string $zip;
    public string $name;
    public string $street;
    public string $city;
    public float $lat;
    public float $lng;
    public string $description = '';
    /** @var array<string, string> day => "08:00–12:00, 13:00–17:00" */
    public array $openingHours = [];
    public string $transportationName;
    public bool $active;
}