<?php

namespace Nevadskiy\Translatable\Strategies\SingleTableExtended;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Nevadskiy\Translatable\Scopes\TranslationsEagerLoadingScope;
use Nevadskiy\Translatable\Strategies\InteractsWithTranslator;
use Nevadskiy\Translatable\Strategies\SingleTable\Models\Translation;
use Nevadskiy\Translatable\Strategies\TranslatorStrategy;

use function count;

/**
 * @mixin Model
 * @property Collection|Translation[] translations
 */
trait HasTranslations
{
    use InteractsWithTranslator;

    /**
     * Boot the translations' trait.
     */
    protected static function bootHasTranslations(): void
    {
        static::addGlobalScope(new TranslationsEagerLoadingScope());

        static::saved(static function (self $translatable) {
            $translatable->handleSavedEvent();
        });

        static::deleted(static function (self $translatable) {
            $translatable->handleDeletedEvent();
        });
    }

    /**
     * Get the translation strategy.
     */
    protected function getTranslationStrategy(): TranslatorStrategy
    {
        return new SingleTableExtendedStrategy($this);
    }

    /**
     * Get the translations' relation.
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(SingleTableExtendedStrategy::model(), 'translatable');
    }

    /**
     * Handle the model "saved" event.
     */
    protected function handleSavedEvent(): void
    {
        $this->translator()->save();
    }

    /**
     * Handle the model deleted event.
     */
    protected function handleDeletedEvent(): void
    {
        $this->translator()->delete();
    }

    /**
     * @inheritDoc
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field = $field ?? $this->getRouteKeyName();

        if (! $this->isTranslatable($field)) {
            return parent::resolveRouteBinding($value, $field);
        }

        return $this->whereTranslatable($field, $value)->first();
    }

    /**
     * Scope to remove translations eager loading from a query.
     */
    protected function scopeWithoutTranslationsScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TranslationsEagerLoadingScope::class);
    }

    /**
     * Scope to eager load translations with the given locales.
     */
    protected function scopeWithTranslations(Builder $query, array $locales = ['*']): Builder
    {
        return $query->withoutTranslationsScope()
            ->with(['translations' => function (Relation $query) use ($locales) {
                if (! (count($locales) === 1 && $locales[0] === '*')) {
                    $query->forLocale($locales);
                }
            }]);
    }

    /**
     * Scope to filter models by translatable attribute.
     */
    protected function scopeWhereTranslatable(Builder $query, string $attribute, $value, string $locale = null, string $operator = '=', string $boolean = 'and'): Builder
    {
        $this->translator()->assertAttributeIsTranslatable($attribute);

        if (null === $locale) {
            return $query->where(function (Builder $query) use ($attribute, $value, $operator) {
                $query->where($attribute, $operator, $value)
                    ->orWhereHas('translations', function (Builder $query) use ($attribute, $value, $operator) {
                        $query->forAttribute($attribute)
                            ->where('value', $operator, $value);
                    });
            }, null, null, $boolean);
        }

        if ($this->translator()->isFallbackLocale($locale)) {
            return $query->where($attribute, $operator, $value, $boolean);
        }

        return $query->has('translations', '>=', 1, $boolean, function (Builder $query) use ($attribute, $value, $locale, $operator) {
            $query->forAttribute($attribute)
                ->forLocale($locale)
                ->where('value', $operator, $value);
        });
    }

    /**
     * Scope to filter models by translatable attribute using the "or" boolean.
     */
    protected function scopeOrWhereTranslatable(Builder $query, string $attribute, $value, string $locale = null, string $operator = '=')
    {
        return $query->whereTranslatable($attribute, $value, $locale, $operator, 'or');
    }

    /**
     * Scope to order models by translatable attribute.
     */
    protected function scopeOrderByTranslatable(Builder $query, string $attribute, string $direction = 'asc', string $locale = null): Builder
    {
        $this->translator()->assertAttributeIsTranslatable($attribute);

        $locale = $locale ?: $this->translator()->getLocale();

        if ($this->translator()->isFallbackLocale($locale)) {
            return $query->orderBy($attribute, $direction);
        }

        $translation = resolve(SingleTableExtendedStrategy::model());

        if (! $query->getQuery()->columns) {
            $query->addSelect($this->qualifyColumn('*'));
        }

        $query->leftJoin($translation->getTable(), function (JoinClause $join) use ($translation, $attribute, $locale) {
            $join->on($translation->qualifyColumn('translatable_id'), '=', $this->qualifyColumn($this->getKeyName()))
                ->where($translation->qualifyColumn('translatable_type'), $this->getMorphClass())
                ->where($translation->qualifyColumn('translatable_attribute'), $attribute)
                ->where($translation->qualifyColumn('locale'), $locale);
        });

        return $query->orderBy($translation->qualifyColumn('value'), $direction);
    }
}
