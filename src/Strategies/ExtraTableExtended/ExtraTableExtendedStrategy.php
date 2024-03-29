<?php

namespace Nevadskiy\Translatable\Strategies\ExtraTableExtended;

use Nevadskiy\Translatable\Strategies\ExtraTable\ExtraTableStrategy;

class ExtraTableExtendedStrategy extends ExtraTableStrategy
{
    /**
     * @inheritdoc
     */
    public function get(string $attribute, string $locale)
    {
        $this->bootIfNotBooted();

        if ($this->translatable->translator()->isFallbackLocale($locale)) {
            return $this->translatable->getRawAttribute($attribute);
        }

        return parent::get($attribute, $locale);
    }

    /**
     * @inheritdoc
     */
    public function set(string $attribute, $value, string $locale): void
    {
        if ($this->translatable->translator()->isFallbackLocale($locale)) {
            $this->translatable->setRawAttribute($attribute, $value);
        } else {
            parent::set($attribute, $value, $locale);
        }
    }

    /**
     * @inheritdoc
     */
    public function getLocalesForEagerLoading(): array
    {
        $locales = [];

        if (! $this->translatable->translator()->isFallbackLocale()) {
            $locales[] = $this->translatable->translator()->getLocale();
        }

        return $locales;
    }
}
