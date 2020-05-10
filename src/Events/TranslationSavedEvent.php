<?php

namespace Nevadskiy\Translatable\Events;

use Nevadskiy\Translatable\Models\Translation;

class TranslationSavedEvent
{
    /**
     * @var Translation
     */
    public $translation;

    /**
     * Create a new event instance.
     *
     * @param Translation $translation
     */
    public function __construct(Translation $translation)
    {
        $this->translation = $translation;
    }
}