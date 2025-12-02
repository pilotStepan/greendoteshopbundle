<?php

namespace Greendot\EshopBundle\I18n;

use Gedmo\Translatable\TranslatableListener;
use Symfony\Contracts\Translation\LocaleAwareInterface;

readonly class GedmoLocaleListener implements LocaleAwareInterface
{
    public function __construct(private TranslatableListener $translatableListener) {}

    public function setLocale(string $locale): void
    {
        $this->translatableListener->setTranslatableLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->translatableListener->getListenerLocale();
    }
}