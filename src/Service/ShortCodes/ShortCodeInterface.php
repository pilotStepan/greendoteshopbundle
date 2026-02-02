<?php

namespace Greendot\EshopBundle\Service\ShortCodes;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Translation\TranslatorTrait;

#[AutoconfigureTag('app.short_code')]
interface ShortCodeInterface
{
    public function supports(string $objectName, ?string $field = null) :bool;
}