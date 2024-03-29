<?php

namespace Nevadskiy\Translatable\Tests\Feature\Strategies\SingleTable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nevadskiy\Translatable\Strategies\SingleTable\Models\Translation;
use Nevadskiy\Translatable\Strategies\SingleTable\HasTranslations;
use Nevadskiy\Translatable\Strategies\SingleTable\SingleTableStrategy;
use Nevadskiy\Translatable\Tests\TestCase;

class CustomGlobalTranslationModelTest extends TestCase
{
    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    /**
     * Set up the database schema.
     */
    private function createSchema(): void
    {
        $this->schema()->create('books', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamps();
        });

        $this->schema()->create('custom_translations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('translatable');
            $table->string('translatable_attribute');
            $table->string('locale', 24);
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /** @test */
    public function it_can_use_custom_global_translation_model(): void
    {
        SingleTableStrategy::useModel(CustomGlobalTranslation::class);

        $book = new BookWithCustomGlobalTranslation();
        $book->translator()->set('title', 'Atlas of animals', 'en');
        $book->translator()->set('title', 'Атлас тварин', 'uk');
        $book->save();

        static::assertInstanceOf(CustomGlobalTranslation::class, $book->translations->first());
        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseCount('custom_translations', 2);
        $this->assertDatabaseHas('custom_translations', [
            'translatable_id' => $book->getKey(),
            'translatable_type' => $book->getMorphClass(),
            'translatable_attribute' => 'title',
            'value' => 'Atlas of animals',
            'locale' => 'en',
        ]);
        $this->assertDatabaseHas('custom_translations', [
            'translatable_id' => $book->getKey(),
            'translatable_type' => $book->getMorphClass(),
            'translatable_attribute' => 'title',
            'value' => 'Атлас тварин',
            'locale' => 'uk',
        ]);
    }

    /** @test */
    public function it_throws_exception_when_using_invalid_translation_model_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("A custom translation model must extend the base translation model.");

        SingleTableStrategy::useModel(InvalidTranslation::class);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        SingleTableStrategy::useModel(Translation::class);
        $this->schema()->drop('books');
        parent::tearDown();
    }
}

/**
 * @property string title
 * @property string|null description
 */
class BookWithCustomGlobalTranslation extends Model
{
    use HasTranslations;

    protected $table = 'books';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $translatable = [
        'title',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $model) {
            $model->setAttribute($model->getKeyName(), Str::uuid());
        });
    }
}

class CustomGlobalTranslation extends Translation
{
    protected $table = 'custom_translations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(static function (self $model) {
            $model->setAttribute($model->getKeyName(), Str::uuid());
        });
    }

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}

class InvalidTranslation extends Model
{
}
