<?php

namespace Nevadskiy\Translatable\Tests\Feature\Strategies\SingleTableExtended;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Nevadskiy\Translatable\Exceptions\AttributeNotTranslatableException;
use Nevadskiy\Translatable\Strategies\SingleTableExtended\HasTranslations;
use Nevadskiy\Translatable\Tests\TestCase;

class WhereTranslatableScopeTest extends TestCase
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
            $table->string('title');
            $table->timestamps();
        });
    }

    /** @test */
    public function it_queries_records_by_translatable_attribute(): void
    {
        $book = new BookWhereTranslatable();
        $book->title = 'The last prophet';
        $book->save();

        $anotherBook = new BookWhereTranslatable();
        $anotherBook->title = 'Forest';
        $anotherBook->save();

        $book->translator()->add('title', 'Останній пророк', 'uk');

        $records = BookWhereTranslatable::query()
            ->whereTranslatable('title', 'Останній пророк')
            ->get();

        static::assertCount(1, $records);
        static::assertTrue($records[0]->is($book));
    }

    /** @test */
    public function it_queries_records_by_translatable_attribute_with_original_value(): void
    {
        $book = new BookWhereTranslatable();
        $book->title = 'The last prophet';
        $book->save();

        $anotherBook = new BookWhereTranslatable();
        $anotherBook->translator()->set('title', 'Forest', 'en');
        $anotherBook->translator()->set('title', 'Останній пророк', 'uk');
        $anotherBook->save();

        $records = BookWhereTranslatable::query()
            ->whereTranslatable('title', 'The last prophet')
            ->get();

        static::assertCount(1, $records);
        static::assertTrue($records[0]->is($book));
    }

    /** @test */
    public function it_queries_records_by_translatable_attribute_and_locale(): void
    {
        $book = new BookWhereTranslatable();
        $book->translator()->add('title', 'The last prophet', 'en');
        $book->translator()->add('title', 'Останній пророк', 'uk');
        $book->save();

        $anotherBook = new BookWhereTranslatable();
        $anotherBook->translator()->set('title', 'Forest', 'en');
        $anotherBook->translator()->set('title', 'Останній пророк', 'pl');
        $anotherBook->save();

        $records = BookWhereTranslatable::query()
            ->whereTranslatable('title', 'Останній пророк', 'uk')
            ->get();

        static::assertCount(1, $records);
        static::assertTrue($records[0]->is($book));
    }

    /** @test */
    public function it_returns_no_results_when_querying_records_by_custom_locale(): void
    {
        $book = new BookWhereTranslatable();
        $book->title = 'The last prophet';
        $book->save();

        $book->translator()->add('title', 'Останній пророк', 'uk');

        $records = BookWhereTranslatable::query()
            ->whereTranslatable('title', 'Останній пророк', 'pl')
            ->get();

        static::assertEmpty($records);
    }

    /** @test */
    public function it_returns_no_results_when_querying_records_by_fallback_locale(): void
    {
        $book = new BookWhereTranslatable();
        $book->title = 'The last prophet';
        $book->save();

        $book->translator()->add('title', 'Останній пророк', 'uk');

        $records = BookWhereTranslatable::query()
            ->whereTranslatable('title', 'Останній пророк', 'en')
            ->get();

        static::assertEmpty($records);
    }

    /** @test */
    public function it_queries_records_by_translatable_attribute_within_all_locales(): void
    {
        $book1 = new BookWhereTranslatable();
        $book1->title = 'The last prophet';
        $book1->save();

        $book1->translator()->add('title', 'Останній пророк', 'uk');

        $book2 = new BookWhereTranslatable();
        $book2->title = 'The last prophet';
        $book2->save();

        $book2->translator()->add('title', 'Останній пророк', 'pl');

        $book3 = new BookWhereTranslatable();
        $book3->title = 'Day of Wrath';
        $book3->save();

        $books = BookWhereTranslatable::query()
            ->whereTranslatable('title', 'Останній пророк')
            ->get();

        static::assertCount(2, $books);
        static::assertTrue($books[0]->is($book1));
        static::assertTrue($books[1]->is($book2));
    }

    /** @test */
    public function it_queries_records_by_translatable_attribute_using_like_operator(): void
    {
        $book1 = new BookWhereTranslatable();
        $book1->title = 'The last prophet';
        $book1->save();

        $book1->translator()->add('title', 'Останній пророк', 'uk');

        $book2 = new BookWhereTranslatable();
        $book2->title = 'The first prophet';
        $book2->save();

        $book2->translator()->add('title', 'Перший пророк', 'uk');

        $book3 = new BookWhereTranslatable();
        $book3->title = 'Day of Wrath';
        $book3->save();

        $records = BookWhereTranslatable::query()
            ->whereTranslatable('title', '%пророк', null, 'LIKE')
            ->get();

        static::assertCount(2, $records);
        static::assertTrue($records[0]->is($book1));
        static::assertTrue($records[1]->is($book2));
    }

    /** @test */
    public function it_throws_exception_when_trying_to_query_by_non_translatable_attribute(): void
    {
        $this->expectException(AttributeNotTranslatableException::class);

        BookWhereTranslatable::query()
            ->whereTranslatable('id', 1)
            ->get();
    }

    /** @test */
    public function it_queries_records_using_or_boolean(): void
    {
        $book1 = new BookWhereTranslatable();
        $book1->translator()->set('title', 'The last prophet', 'en');
        $book1->translator()->set('title', 'Останній пророк', 'uk');
        $book1->save();

        $book2 = new BookWhereTranslatable();
        $book2->translator()->set('title', 'The first prophet', 'en');
        $book2->save();

        $book3 = new BookWhereTranslatable();
        $book3->translator()->set('title', 'Day of Wrath', 'en');
        $book3->save();

        $records = BookWhereTranslatable::query()
            ->whereTranslatable('title', '%пророк', null, 'LIKE')
            ->whereTranslatable('title', 'Day of Wrath', 'en', '=', 'or')
            ->get();

        static::assertCount(2, $records);
        static::assertTrue($records[0]->is($book1));
        static::assertTrue($records[1]->is($book3));
    }

    /** @test */
    public function it_queries_records_using_and_boolean_by_default(): void
    {
        $book = new BookWhereTranslatable();
        $book->translator()->set('title', 'The last prophet', 'en');
        $book->translator()->set('title', 'Останній пророк', 'uk');
        $book->save();

        $records = BookWhereTranslatable::query()
            ->whereTranslatable('title', '%пророк', null, 'LIKE')
            ->whereTranslatable('title', 'Day of Wrath', 'en')
            ->get();

        static::assertCount(0, $records);
    }

    /** @test */
    public function it_queries_records_using_or_boolean_in_custom_locale(): void
    {
        $book1 = new BookWhereTranslatable();
        $book1->translator()->set('title', 'The last prophet', 'en');
        $book1->translator()->set('title', 'Останній пророк', 'uk');
        $book1->save();

        $book2 = new BookWhereTranslatable();
        $book2->translator()->set('title', 'The first prophet', 'en');
        $book2->save();

        $book3 = new BookWhereTranslatable();
        $book3->translator()->set('title', 'Day of Wrath', 'en');
        $book3->translator()->set('title', 'День гніву', 'uk');
        $book3->save();

        $records = BookWhereTranslatable::query()
            ->whereTranslatable('title', 'Останній пророк')
            ->whereTranslatable('title', 'День гніву', 'uk', '=', 'or')
            ->get();

        static::assertCount(2, $records);
        static::assertTrue($records[0]->is($book1));
        static::assertTrue($records[1]->is($book3));
    }

    /** @test */
    public function it_queries_records_using_or_boolean_in_nullable_locale(): void
    {
        $book1 = new BookWhereTranslatable();
        $book1->translator()->set('title', 'The last prophet', 'en');
        $book1->translator()->set('title', 'Останній пророк', 'uk');
        $book1->save();

        $book2 = new BookWhereTranslatable();
        $book2->translator()->set('title', 'The first prophet', 'en');
        $book2->save();

        $book3 = new BookWhereTranslatable();
        $book3->translator()->set('title', 'Day of Wrath', 'en');
        $book3->translator()->set('title', 'День гніву', 'uk');
        $book3->save();

        $records = BookWhereTranslatable::query()
            ->whereTranslatable('title', 'Останній пророк')
            ->whereTranslatable('title', 'День гніву', null, '=', 'or')
            ->get();

        static::assertCount(2, $records);
        static::assertTrue($records[0]->is($book1));
        static::assertTrue($records[1]->is($book3));
    }

    /** @test */
    public function it_queries_records_using_or_boolean_helper(): void
    {
        $book1 = new BookWhereTranslatable();
        $book1->translator()->set('title', 'The last prophet', 'en');
        $book1->translator()->set('title', 'Останній пророк', 'uk');
        $book1->save();

        $book2 = new BookWhereTranslatable();
        $book2->translator()->set('title', 'The first prophet', 'en');
        $book2->save();

        $book3 = new BookWhereTranslatable();
        $book3->translator()->set('title', 'Day of Wrath', 'en');
        $book3->save();

        $records = BookWhereTranslatable::query()
            ->whereTranslatable('title', 'Останній пророк', 'uk')
            ->orWhereTranslatable('title', 'Day of Wrath', 'en')
            ->get();

        static::assertCount(2, $records);
        static::assertTrue($records[0]->is($book1));
        static::assertTrue($records[1]->is($book3));
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('books');
        parent::tearDown();
    }
}

/**
 * @property string title
 */
class BookWhereTranslatable extends Model
{
    use HasTranslations;

    protected $table = 'books';

    protected $translatable = [
        'title',
    ];
}
