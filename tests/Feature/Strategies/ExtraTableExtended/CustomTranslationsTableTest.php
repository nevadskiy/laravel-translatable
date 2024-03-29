<?php

namespace Nevadskiy\Translatable\Tests\Feature\Strategies\ExtraTableExtended;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Nevadskiy\Translatable\Strategies\ExtraTableExtended\HasTranslations;
use Nevadskiy\Translatable\Tests\TestCase;

class CustomTranslationsTableTest extends TestCase
{
    /**
     * @inheritDoc
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

        $this->schema()->create('book_entity_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id');
            $table->string('title');
            $table->string('locale');
            $table->timestamps();
        });
    }

    /** @test */
    public function it_stores_translations_using_custom_translations_table(): void
    {
        $book = new BookWithCustomTranslationsTable();
        $book->translator()->set('title', 'Forest song', 'en');
        $book->translator()->set('title', 'Лісова пісня', 'uk');
        $book->save();

        $this->assertDatabaseHas('books', [
            'title' => 'Forest song',
        ]);
        $this->assertDatabaseHas('book_entity_translations', [
            'title' => 'Лісова пісня',
            'locale' => 'uk',
        ]);
    }

    /** @test */
    public function it_stores_translations_using_custom_foreign_key(): void
    {
        $book = new BookWithCustomTranslationsTable();
        $book->translator()->set('title', 'Forest song', 'en');
        $book->translator()->set('title', 'Лісова пісня', 'uk');
        $book->save();

        $this->assertDatabaseHas('books', [
            'title' => 'Forest song',
        ]);
        $this->assertDatabaseHas('book_entity_translations', [
            'entity_id' => $book->getKey(),
            'title' => 'Лісова пісня',
            'locale' => 'uk',
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('book_entity_translations');
        $this->schema()->drop('books');
        parent::tearDown();
    }
}

/**
 * @property string title
 */
class BookWithCustomTranslationsTable extends Model
{
    use HasTranslations;

    protected $table = 'books';

    protected $translatable = [
        'title',
    ];

    protected function getEntityTranslationTable(): string
    {
        return 'book_entity_translations';
    }

    protected function getEntityTranslationForeignKey(): string
    {
        return 'entity_id';
    }
}
