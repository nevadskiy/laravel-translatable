<?php

namespace Nevadskiy\Translatable\Tests\Feature\Strategies\ExtraTable;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Nevadskiy\Translatable\Exceptions\AttributeNotTranslatableException;
use Nevadskiy\Translatable\Exceptions\TranslationMissingException;
use Nevadskiy\Translatable\Strategies\ExtraTable\HasTranslations;
use Nevadskiy\Translatable\Tests\TestCase;

class ExtraTableStrategyTest extends TestCase
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
            $table->string('description')->nullable();
            $table->string('locale');
            $table->timestamps();
        });
    }

    /** @test */
    public function it_stores_translations_using_extra_table_strategy(): void
    {
        $book = new Book();
        $book->translator()->set('title', 'Amazing birds', 'en');
        $book->translator()->set('title', 'Дивовижні птахи', 'uk');
        $book->translator()->set('description', 'This book will help you discover all the secrets of birds', 'en');
        $book->translator()->set('description', 'Ця книга допоможе тобі вивідати всі пташині таємниці', 'uk');
        $book->save();

        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseCount('book_translations', 2);
        $this->assertDatabaseHas('book_translations', [
            'book_id' => $book->getKey(),
            'title' => 'Amazing birds',
            'description' => 'This book will help you discover all the secrets of birds',
            'locale' => 'en',
        ]);
        $this->assertDatabaseHas('book_translations', [
            'book_id' => $book->getKey(),
            'title' => 'Дивовижні птахи',
            'description' => 'Ця книга допоможе тобі вивідати всі пташині таємниці',
            'locale' => 'uk',
        ]);
    }

    /** @test */
    public function it_retrieves_translations_using_extra_table_strategy(): void
    {
        $book = new Book();
        $book->translator()->set('title', 'Amazing birds', 'en');
        $book->translator()->set('title', 'Дивовижні птахи', 'uk');
        $book->translator()->set('description', 'This book will help you discover all the secrets of birds', 'en');
        $book->translator()->set('description', 'Ця книга допоможе тобі вивідати всі пташині таємниці', 'uk');
        $book->save();

        static::assertSame('Amazing birds', $book->translator()->get('title', 'en'));
        static::assertSame('Дивовижні птахи', $book->translator()->get('title', 'uk'));
        static::assertSame('This book will help you discover all the secrets of birds', $book->translator()->get('description', 'en'));
        static::assertSame('Ця книга допоможе тобі вивідати всі пташині таємниці', $book->translator()->get('description', 'uk'));
    }

    /** @test */
    public function it_stores_translations_using_attribute_interceptors_with_extra_table_strategy(): void
    {
        $book = new Book();

        $this->app->setLocale('en');
        $book->title = 'Amazing birds';

        $this->app->setLocale('uk');
        $book->title = 'Дивовижні птахи';

        $book->save();

        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseCount('book_translations', 2);
        $this->assertDatabaseHas('book_translations', [
            'book_id' => $book->getKey(),
            'title' => 'Amazing birds',
            'locale' => 'en',
        ]);
        $this->assertDatabaseHas('book_translations', [
            'book_id' => $book->getKey(),
            'title' => 'Дивовижні птахи',
            'locale' => 'uk',
        ]);
    }

    // TODO: extract into SetterTranslationTest
    /** @test */
    public function it_does_not_store_translations_using_attribute_interceptors_without_save_call(): void
    {
        $book = new Book();

        $this->app->setLocale('en');
        $book->title = 'Amazing birds';

        $this->app->setLocale('uk');
        $book->title = 'Дивовижні птахи';

        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('book_translations', 0);
    }

    /** @test */
    public function it_retrieves_translations_using_attribute_interceptors_on_single_table_strategy(): void
    {
        $book = new Book();
        $book->translator()->set('title', 'Amazing birds', 'en');
        $book->translator()->set('title', 'Дивовижні птахи', 'uk');
        $book->save();

        $this->app->setLocale('en');
        static::assertSame('Amazing birds', $book->title);

        $this->app->setLocale('uk');
        static::assertSame('Дивовижні птахи', $book->title);
    }

    /** @test */
    public function it_fills_translatable_attributes_with_nulls_when_translations_are_missing(): void
    {
        $book = new Book();
        $book->save();

        $array = $book->toArray();

        static::assertNull($array['title']);
        static::assertNull($array['description']);
    }

    /** @test */
    public function it_throws_exception_when_trying_to_get_missing_translation(): void
    {
        $book = new Book();
        $book->title = 'Large encyclopedia of animals';
        $book->save();

        $this->expectException(TranslationMissingException::class);

        $book->translator()->getOrFail('title', 'uk');
    }

    /** @test */
    public function it_throws_exception_when_trying_to_get_translation_for_non_translatable_attribute(): void
    {
        $book = new Book();
        $book->title = 'Large encyclopedia of animals';
        $book->save();

        $this->expectException(AttributeNotTranslatableException::class);

        $book->translator()->get('created_at');
    }

    /** @test */
    public function it_throws_exception_when_trying_to_add_translation_for_non_translatable_attribute(): void
    {
        $book = new Book();
        $book->title = 'Large encyclopedia of animals';
        $book->save();

        try {
            $book->translator()->add('created_at', now()->setTimezone('Europe/Kiev'), 'uk');
            static::fail('Exception was not thrown for not translatable attribute');
        } catch (AttributeNotTranslatableException $e) {
            $this->assertDatabaseCount('book_translations', 1);
        }
    }

    /** @test */
    public function it_returns_fallback_value_if_translation_is_missing(): void
    {
        $book = new Book();
        $book->title = 'Atlas of animals';
        $book->save();

        static::assertSame('Atlas of animals', $book->translator()->get('title', 'uk'));
    }

    /** @test */
    public function it_returns_null_if_translation_is_nullable(): void
    {
        $book = new Book();
        $book->translator()->set('title', 'Світ навколо нас', 'uk');
        $book->translator()->set('description', null, 'uk');
        $book->save();

        static::assertNull($book->translator()->get('description', 'uk'));
    }

    /** @test */
    public function it_overrides_previous_translations(): void
    {
        $book = new Book();
        $book->translator()->set('title', 'The world around us. Wild animals', 'en');
        $book->translator()->set('title', 'Світ навколо нас', 'uk');
        $book->save();

        static::assertSame('Світ навколо нас', $book->translator()->get('title', 'uk'));

        $book->translator()->add('title', 'Світ навколо нас. Дикі тварини', 'uk');

        static::assertSame('Світ навколо нас. Дикі тварини', $book->translator()->get('title', 'uk'));
        $this->assertDatabaseCount('book_translations', 2);
    }

    /** @test */
    public function it_does_not_store_pending_translations_twice(): void
    {
        $book = new Book();
        $book->title = 'The world around us';
        $book->save();

        $this->app->setLocale('uk');
        $book->translator()->set('title', 'Світ навколо нас', 'uk');
        $book->save();

        $this->app[ConnectionInterface::class]->enableQueryLog();

        $book->save();

        static::assertEmpty($this->app[ConnectionInterface::class]->getQueryLog());
        $this->assertDatabaseCount('book_translations', 2);
    }

    /** @test */
    public function it_does_not_duplicate_translations(): void
    {
        $book = new Book();
        $book->title = 'The world around us';
        $book->save();

        $book->translator()->add('title', 'Світ навколо нас', 'uk');
        $book->translator()->add('title', 'Світ навколо нас', 'uk');

        $this->assertDatabaseCount('book_translations', 2);
    }

    /** @test */
    public function it_performs_only_one_query_to_retrieve_translation_for_same_attribute_and_locale(): void
    {
        $book = new Book();
        $book->translator()->add('title', 'The world around us', 'en');
        $book->translator()->add('title', 'Світ навколо нас', 'uk');
        $book->save();

        $book = $book->fresh();

        $this->app[ConnectionInterface::class]->enableQueryLog();

        $book->translator()->get('title', 'uk');
        $book->translator()->get('title', 'uk');
        $book->translator()->get('title', 'uk');

        static::assertCount(1, $this->app[ConnectionInterface::class]->getQueryLog());
    }

    /** @test */
    public function it_clears_pending_translations_after_saving(): void
    {
        $book = new Book();
        $book->translator()->set('title', 'Amazing birds', 'en');
        $book->translator()->set('description', 'This book will help you discover all the secrets of birds', 'en');

        static::assertSame(['en' => [
            'title' => 'Amazing birds',
            'description' => 'This book will help you discover all the secrets of birds',
        ]], $book->translator()->getStrategy()->getPendingTranslations());

        $book->save();

        static::assertSame([], $book->translator()->getStrategy()->getPendingTranslations());
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
 * @property string title
 * @property string|null description
 */
class Book extends Model
{
    use HasTranslations;

    protected $table = 'books';

    protected $translatable = [
        'title',
        'description',
    ];
}
