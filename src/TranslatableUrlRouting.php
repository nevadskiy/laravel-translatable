<?php

namespace Nevadskiy\Translatable;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 * @mixin HasTranslations
 */
trait TranslatableUrlRouting
{
    /**
     * Retrieve the model for a bound value.
     *
     * @param mixed $value
     * @param string|null $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        if (! $this->shouldResolveBindingUsingTranslations($field)) {
            return parent::resolveRouteBinding($value, $field);
        }

        $locale = static::getTranslator()->getLocale();

        $model = $this->whereTranslatable($field, $value, $locale)->first();

        if ($model) {
            return $model;
        }

        return $this->newQuery()
            ->where($field, $value)
            ->whereDoesntHave('translations', function ($query) use ($field, $locale) {
                $query->forAttribute($field);
                $query->forLocale($locale);
                $query->where('is_archived', false);
            })
            ->first();
    }

    /**
     * Determine whether it should resolve route binding using translations.
     */
    protected function shouldResolveBindingUsingTranslations(string $field): bool
    {
        return $this->isTranslatable($field)
            && ! static::getTranslator()->isDefaultLocale();
    }
}
