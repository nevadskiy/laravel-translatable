<?php

namespace Nevadskiy\Translatable\Strategies\Single;

use Illuminate\Database\Eloquent\Model;
use Nevadskiy\Translatable\Strategies\Single\Models\Translation;
use Nevadskiy\Translatable\Strategies\TranslatorStrategy;

/**
 * TODO: add possibility to trigger an exception when creating model in non-default locale.
 * TODO: add possibility to extract translatable attributes out of the model into single table (allows to create models in custom locale)
 * TODO: structure translations on 'retrieve' event (only when it was fired AFTER eager loading, not before like now)
 * TODO: structure loaded translation in the strategy as this: ['en' => ['title' => null], 'uk' => ['title' => 'Книга']]
 */
class SingleTableStrategy implements TranslatorStrategy
{
    // TODO: boot translation similar how laravel model is doing this (original and translations array and dirty on save)

    /**
     * The translatable model instance.
     *
     * @var Model|HasTranslations
     */
    private $model;

    /**
     * A list of pending translation insertions.
     *
     * @var array
     */
    private $pendingTranslations = [];

    /**
     * Make a new strategy instance.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * @inheritdoc
     */
    public function get(string $attribute, string $locale)
    {
        if ($this->shouldGetFromOriginalAttribute($locale)) {
            return $this->model->getOriginalAttribute($attribute);
        }

        if (isset($this->pendingTranslations[$locale][$attribute])) {
            return $this->pendingTranslations[$locale][$attribute];
        }

        return $this->getFromRelation($attribute, $locale);
    }

    /**
     * @inheritdoc
     */
    public function set(string $attribute, $value, string $locale): void
    {
        if ($this->shouldSetToOriginalAttribute($locale)) {
            $this->model->setOriginalAttribute($attribute, $value);
        } else {
            $this->pendingTranslations[$locale][$attribute] = $this->model->withAttributeSetter($attribute, $value);
        }
    }

    /**
     * Determine if the given locale is fallback locale.
     */
    private function isFallbackLocale(string $locale): bool
    {
        return $locale === 'en';
    }

    public function save(): void
    {
        foreach ($this->pullPendingTranslations() as $locale => $attributes) {
            foreach (array_filter($attributes) as $attribute => $value) {
                $this->model->translations()->updateOrCreate([
                    'translatable_attribute' => $attribute,
                    'locale' => $locale,
                ], [
                    'value' => $value,
                ]);
            }
        }
    }

    private function pullPendingTranslations(): array
    {
        $pendingTranslations = $this->pendingTranslations;

        $this->pendingTranslations = [];

        return $pendingTranslations;
    }

    /**
     * @param string $locale
     * @return bool
     */
    private function shouldSetToOriginalAttribute(string $locale): bool
    {
        if (! $this->model->exists) {
            return true;
        }

        return $this->isFallbackLocale($locale);
    }

    private function shouldGetFromOriginalAttribute(string $locale): bool
    {
        return $this->isFallbackLocale($locale);
    }

    /**
     * @param string $attribute
     * @param string $locale
     * @return mixed
     */
    private function getFromRelation(string $attribute, string $locale)
    {
        $translation = $this->model->translations->first(function (Translation $translation) use ($attribute, $locale) {
            return $translation->translatable_attribute === $attribute
                && $translation->locale === $locale;
        });

        if (! $translation) {
            return null;
        }

        return $translation->getAttribute('value');
    }
}