<?php

namespace Nevadskiy\Translatable\Strategies\SingleTable;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Nevadskiy\Translatable\Strategies\RelationTranslatorStrategy;
use Nevadskiy\Translatable\Strategies\SingleTable\Models\Translation;

class SingleTableStrategy extends RelationTranslatorStrategy
{
    /**
     * The default mode class of the strategy.
     *
     * @var string
     */
    protected static $model = Translation::class;

    /**
     * Specify the translation model class.
     */
    public static function useModel(string $model): void
    {
        if (! is_a($model, Translation::class, true)) {
            throw new InvalidArgumentException('A custom translation model must extend the base translation model.');
        }

        static::$model = $model;
    }

    /**
     * Get the model class.
     */
    public static function model(): string
    {
        return static::$model;
    }

    /**
     * @inheritdoc
     */
    protected function loadTranslations(Collection $translations): void
    {
        $translations->each(function (Translation $translation) {
            $this->translations[$translation->locale][$translation->translatable_attribute] = $translation->value;
        });
    }

    /**
     * @inheritdoc
     */
    protected function saveTranslations(array $translations): void
    {
        foreach ($translations as $locale => $attributes) {
            foreach ($attributes as $attribute => $value) {
                $this->translatable->translations()->updateOrCreate([
                    'translatable_attribute' => $attribute,
                    'locale' => $locale,
                ], [
                    'value' => $value,
                ]);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getLocalesForEagerLoading(): array
    {
        $locales = [];

        if ($this->translatable->translator()->shouldFallback()) {
            $locales[] = $this->translatable->translator()->getFallbackLocale();
        }

        if (! $this->translatable->translator()->isFallbackLocale()) {
            $locales[] = $this->translatable->translator()->getLocale();
        }

        return $locales;
    }
}
