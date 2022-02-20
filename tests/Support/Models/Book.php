<?php

namespace Nevadskiy\Translatable\Tests\Support\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Nevadskiy\Translatable\HasTranslations;
use Nevadskiy\Uuid\Uuid;

/**
 * @property string id
 * @property string title
 * @property string description
 * @property int version
 * @property string description_short
 * @property Collection translations
 * @property Carbon created_at
 * @property Carbon updated_at
 */
class Book extends Model
{
    use Uuid,
        HasTranslations;

    /**
     * The attributes that can be translatable.
     *
     * @var array
     */
    protected $translatable = [
        'title',
        'description',
    ];

    /**
     * Get title attribute.
     */
    public function getTitleAttribute(string $title): string
    {
        return Str::ucfirst($title);
    }
}
