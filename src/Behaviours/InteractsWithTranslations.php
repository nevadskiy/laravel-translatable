<?php

namespace Nevadskiy\Translatable\Behaviours;

use Illuminate\Database\Eloquent\Model;
use Nevadskiy\Translatable\Strategies\SingleTableStrategy;
use Nevadskiy\Translatable\Strategies\TranslatorStrategy;
use Nevadskiy\Translatable\Translator;

/**
 * @mixin Model
 */
trait InteractsWithTranslations
{
    /**
     * The model translator instance.
     */
    protected $translator;

    /**
     * Init the trait.
     */
    protected function initializeInteractsWithTranslations(): void
    {
        $this->translator = $this->newTranslator();
    }

    /**
     * Get the translator instance for the model.
     */
    public function translation(): Translator
    {
        return $this->translator;
    }

    /**
     * Make a new translator instance for the model.
     */
    public function newTranslator(): Translator
    {
        return new Translator($this, $this->getTranslationStrategy());
    }

    /**
     * Get the translation strategy.
     * TODO: probably make it an abstract.
     */
    protected function getTranslationStrategy(): TranslatorStrategy
    {
        return new SingleTableStrategy($this);
    }

    /**
     * Get an attribute from the model.
     *
     * @param string $attribute
     * @return mixed
     */
    public function getAttribute($attribute)
    {
        if (! $this->shouldProxyAttributeToTranslation($attribute)) {
            return $this->getOriginalAttribute($attribute);
        }

        if (! $this->isTranslatable($attribute)) {
            return $this->getOriginalAttribute($attribute);
        }

        return $this->translation()->getOrFallback($attribute);
    }

    /**
     * Get attribute's default value without translation.
     *
     * @return mixed
     */
    public function getOriginalAttribute(string $attribute)
    {
        return parent::getAttribute($attribute);
    }

    /**
     * Get the attribute value with all accessors and casts applied.
     *
     * @param mixed $value
     * @return mixed
     */
    public function withAttributeGetter(string $key, $value)
    {
        if (isset($this->attributes[$key])) {
            $original = $this->attributes[$key];
        }

        $this->attributes[$key] = $value;

        $processed = parent::getAttribute($key);

        if (isset($original)) {
            $this->attributes[$key] = $original;
        } else {
            unset($this->attributes[$key]);
        }

        return $processed;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $attribute
     * @param mixed $value
     * @return mixed
     */
    public function setAttribute($attribute, $value)
    {
        if (! $this->shouldProxyAttributeToTranslation($attribute)) {
            return $this->setOriginalAttribute($attribute, $value);
        }

        if (! $this->isTranslatable($attribute)) {
            return $this->setOriginalAttribute($attribute, $value);
        }

        $this->translation()->set($attribute, $value);

        return $this;
    }

    /**
     * Set attribute's value without translation.
     *
     * @param mixed $value
     * @return mixed
     */
    public function setOriginalAttribute(string $attribute, $value)
    {
        return parent::setAttribute($attribute, $value);
    }

    /**
     * Get the attribute value with all mutators and casts applied.
     *
     * @param mixed $value
     * @return mixed
     */
    public function withAttributeSetter(string $key, $value)
    {
        if (isset($this->attributes[$key])) {
            $original = $this->attributes[$key];
        }

        parent::setAttribute($key, $value);

        $processed = $this->attributes[$key];

        if (isset($original)) {
            $this->attributes[$key] = $original;
        } else {
            unset($this->attributes[$key]);
        }

        return $processed;
    }

    /**
     * Determine if the model should proxy the attribute to a translation bag.
     */
    public function shouldProxyAttributeToTranslation(string $attribute): bool
    {
        return true;
    }

    /**
     * Determine whether the attribute is translatable.
     */
    public function isTranslatable(string $attribute): bool
    {
        return in_array($attribute, $this->getTranslatable(), true);
    }

    /**
     * Get translatable attributes.
     */
    public function getTranslatable(): array
    {
        return $this->translatable ?? [];
    }

    /**
     * Convert the model's attributes to an array.
     */
    public function attributesToArray(): array
    {
        return array_merge(parent::attributesToArray(), $this->translation()->toArray());
    }
}
