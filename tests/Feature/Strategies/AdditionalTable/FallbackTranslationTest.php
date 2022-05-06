<?php

namespace Nevadskiy\Translatable\Tests\Feature\Strategies\AdditionalTable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Nevadskiy\Translatable\Strategies\AdditionalTable\HasTranslations;
use Nevadskiy\Translatable\Tests\TestCase;

class FallbackTranslationTest extends TestCase
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
            $table->id();
            $table->timestamps();
        });

        $this->schema()->create('book_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id');
            $table->string('title');
            $table->string('locale');
            $table->timestamps();
        });
    }

    /** @test */
    public function it_retrieves_fallback_translation(): void
    {
        $book = new BookWithFallback();
        $book->translator()->set('title', 'Sense gallery', $this->app->getFallbackLocale());
        $book->translator()->set('title', 'Галерея чуття', 'uk');
        $book->save();

        $this->app->setLocale('uk');
        self::assertEquals('Sense gallery', $book->translator()->getFallback('title'));
    }

    /** @test */
    public function it_retrieves_fallback_translation_when_translation_is_missing_for_custom_locale(): void
    {
        $book = new BookWithFallback();
        $book->translator()->set('title', 'Sense gallery', $this->app->getFallbackLocale());
        $book->save();

        $this->app->setLocale('uk');

        self::assertEquals('Sense gallery', $book->translator()->get('title', 'uk'));
    }

    /** @test */
    public function it_updates_fallback_translation_when_adding_translation_in_fallback_locale(): void
    {
        $book = new Book();
        $book->title = 'Encyclopedia of animals';
        $book->save();

        $book->translator()->add('title', 'Large encyclopedia of animals', $this->app->getFallbackLocale());

        self::assertEquals('Large encyclopedia of animals', $book->title);
        $this->assertDatabaseCount('book_translations', 1);
        $this->assertDatabaseHas('book_translations', [
            'title' => 'Large encyclopedia of animals',
            'locale' => $this->app->getFallbackLocale(),
        ]);
    }

    /** @test */
    public function it_retrieves_fallback_translation_with_accessor_applied(): void
    {
        $book = new BookWithFallback();
        $book->translator()->set('title', 'sense gallery', $this->app->getFallbackLocale());
        $book->translator()->set('title', 'галерея чуття', 'uk');
        $book->save();

        $this->app->setLocale('uk');
        self::assertEquals('Sense gallery', $book->translator()->getFallback('title'));
    }

    /** @test */
    public function it_stores_translation_from_attribute_interceptor_in_fallback_locale(): void
    {
        $book = new BookWithFallback();
        $book->title = 'sense gallery';
        $book->save();

        $this->assertDatabaseHas('books', ['id' => $book->getKey()]);
        $this->assertDatabaseHas('book_translations', [
            'title' => 'sense gallery',
            'locale' => $this->app->getFallbackLocale(),
        ]);
    }

    /** @test */
    public function it_retrieves_raw_fallback_translation(): void
    {
        $book = new BookWithFallback();
        $book->title = 'sense gallery';
        $book->save();

        $this->app->setLocale('uk');
        $book->translator()->add('title', 'галерея чуття', 'uk');

        self::assertEquals('sense gallery', $book->translator()->getRawFallback('title'));
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('book_translations');
        $this->schema()->drop('books');
        parent::tearDown();
    }
}

/**
 * @property string|null title
 */
class BookWithFallback extends Model
{
    use HasTranslations;

    protected $table = 'books';

    protected $translatable = [
        'title',
    ];

    protected function getEntityTranslationTable(): string
    {
        return 'book_translations';
    }

    protected function getEntityTranslationForeignKey(): string
    {
        return 'book_id';
    }

    public function getTitleAttribute(string $title): string
    {
        return Str::ucfirst($title);
    }
}
