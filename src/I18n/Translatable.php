<?php

namespace Greendot\EshopBundle\I18n;

use Gedmo\Translatable\Translatable as GedmoTranslatable;

interface Translatable extends GedmoTranslatable
{
    public function getTranslatableLocale(): ?string;

    public function setTranslatableLocale(?string $locale): void;
}