<?php

namespace Nevadskiy\Translatable\Tests\Unit\Strategies\ExtraTable\Models;

use Illuminate\Database\Schema\Blueprint;
use Nevadskiy\Translatable\Strategies\ExtraTable\Models\Translation;
use Nevadskiy\Translatable\Tests\TestCase;
use RuntimeException;

class TranslationTest extends TestCase
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
        $this->schema()->create('book_translations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('locale');
            $table->timestamps();
        });
    }

    /** @test */
    public function it_can_be_scoped_by_locale(): void
    {
        $translation1 = new Translation();
        $translation1->setTable('book_translations');
        $translation1->title = 'Отак загинув Гуска';
        $translation1->locale = 'uk';
        $translation1->save();

        $translation2 = new Translation();
        $translation2->setTable('book_translations');
        $translation2->title = 'This is how Guska died';
        $translation2->locale = 'en';
        $translation2->save();

        $translations = $this->model()
            ->newQuery()
            ->forLocale('uk')
            ->get();

        static::assertCount(1, $translations);
        static::assertTrue($translations->first()->is($translation1));
    }

    /** @test */
    public function it_can_be_scoped_by_array_of_locales(): void
    {
        $translation1 = $this->model();
        $translation1->title = 'Отак загинув Гуска';
        $translation1->locale = 'uk';
        $translation1->save();

        $translation2 = $this->model();
        $translation2->title = 'This is how Guska died';
        $translation2->locale = 'en';
        $translation2->save();

        $translation3 = $this->model();
        $translation3->title = 'Tak zginęła Guska';
        $translation3->locale = 'pl';
        $translation3->save();

        $translations = $this->model()
            ->newQuery()
            ->forLocale(['uk', 'pl'])
            ->get();

        static::assertCount(2, $translations);
        static::assertTrue($translations->contains($translation1));
        static::assertTrue($translations->contains($translation3));
    }

    /** @test */
    public function it_throws_exception_when_table_is_not_manually_set(): void
    {
        $translation = new Translation();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Table is not defined for Translation model.");

        $translation->getTable();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('book_translations');
        parent::tearDown();
    }

    /**
     * Get the translation model instance.
     */
    protected function model(): Translation
    {
        return (new Translation())->setTable('book_translations');
    }
}
