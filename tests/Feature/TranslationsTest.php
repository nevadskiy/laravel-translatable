<?php

namespace Nevadskiy\Translatable\Tests\Feature;

use Nevadskiy\Translatable\Exceptions\AttributeNotTranslatableException;
use Nevadskiy\Translatable\Models\Translation;
use Nevadskiy\Translatable\Tests\Support\Factories\BookFactory;
use Nevadskiy\Translatable\Tests\TestCase;

class TranslationsTest extends TestCase
{
    /** @test */
    public function it_saves_translations_for_translatable_attributes(): void
    {
        $book = BookFactory::new()->create();

        $book->translation()->add('title', 'Моя тестовая книга', 'ru');

        self::assertEquals('Моя тестовая книга', $book->translation()->get('title', 'ru'));
    }

    /** @test */
    public function it_returns_correct_value_from_multiple_translations(): void
    {
        $book = BookFactory::new()->create();

        $book->translation()->add('title', 'Моя блестящая книга', 'ru');
        $book->translation()->add('title', 'Mi brillante libro', 'es');
        $book->translation()->add('title', 'Mon brillant livre', 'fr');

        self::assertEquals('Mi brillante libro', $book->translation()->get('title', 'es'));
    }

    /** @test */
    public function it_does_not_break_anything_for_default_attributes(): void
    {
        $book = BookFactory::new()->create(['version' => 24]);

        self::assertEquals(24, $book->version);
    }

    /** @test */
    public function it_returns_correct_value_from_multiple_attributes(): void
    {
        $book = BookFactory::new()->create();

        $book->translation()->add('title', 'Книга о птицах', 'ru');
        $book->translation()->add('description', 'Livre sur les oiseaux', 'fr');

        self::assertEquals('Книга о птицах', $book->translation()->get('title', 'ru'));
    }

    /** @test */
    public function it_does_not_override_default_values(): void
    {
        $book = BookFactory::new()->create(['title' => 'My original book']);

        $book->translation()->add('title', 'Моя оригинальная книга', 'ru');

        $book = $book->fresh();

        self::assertEquals('Моя оригинальная книга', $book->translation()->get('title', 'ru'));
        self::assertEquals('My original book', $book->title);
    }

    /** @test */
    public function it_returns_null_if_translation_does_not_exist(): void
    {
        self::assertNull(
            BookFactory::new()->create()->translation()->get('title', 'fr')
        );
    }

    /** @test */
    public function it_returns_default_value_correctly_if_translation_does_not_exist(): void
    {
        $book = BookFactory::new()->create(['title' => 'English title']);

        self::assertEquals('English title', $book->getAttribute('title'));
    }

    /** @test */
    public function it_saves_translations_to_the_database(): void
    {
        $book = BookFactory::new()->create();

        $book->translation()->add('title', 'Моя новая книга', 'ru');

        self::assertCount(1, Translation::all());

        $this->assertDatabaseHas('translations', [
            'translatable_id' => $book->id,
            'translatable_type' => $book->getMorphClass(),
            'translatable_attribute' => 'title',
            'value' => 'Моя новая книга',
            'locale' => 'ru',
        ]);
    }

    /** @test */
    public function it_saves_many_translations_for_translatable_attributes(): void
    {
        $book = BookFactory::new()->create();

        $book->translation()->addMany([
            'title' => 'Тестовое название книги',
            'description' => 'Тестовое описание книги',
        ], 'ru');

        self::assertEquals('Тестовое название книги', $book->translation()->get('title', 'ru'));
        self::assertEquals('Тестовое описание книги', $book->translation()->get('description', 'ru'));
    }

    /** @test */
    public function it_saves_many_translations_to_the_database(): void
    {
        $book = BookFactory::new()->create();

        $book->translation()->addMany([
            'title' => 'Моя новая книга',
            'description' => 'Как хранить переводы для Laravel',
        ], 'ru');

        self::assertCount(2, Translation::all());

        $this->assertDatabaseHas('translations', [
            'translatable_id' => $book->id,
            'translatable_type' => $book->getMorphClass(),
            'translatable_attribute' => 'title',
            'value' => 'Моя новая книга',
            'locale' => 'ru',
        ]);

        $this->assertDatabaseHas('translations', [
            'translatable_id' => $book->id,
            'translatable_type' => $book->getMorphClass(),
            'translatable_attribute' => 'description',
            'value' => 'Как хранить переводы для Laravel',
            'locale' => 'ru',
        ]);
    }

    /** @test */
    public function it_returns_translation_from_different_locales(): void
    {
        $book = BookFactory::new()->create(['title' => 'My original title']);

        $book->translation()->add('title', 'Min ursprungliga titel', 'sv');
        $book->translation()->add('title', 'Mi titulo original', 'es');

        self::assertEquals('Min ursprungliga titel', $book->translation()->get('title', 'sv'));
        self::assertEquals('Mi titulo original', $book->translation()->get('title', 'es'));
        self::assertEquals('My original title', $book->title);
    }

    /** @test */
    public function it_overrides_previous_translations(): void
    {
        $book = BookFactory::new()->create();

        $book->translation()->add('title', 'Неправильное название книги', 'ru');
        $book->translation()->add('title', 'Правильное название книги', 'ru');

        self::assertCount(1, Translation::all());
        self::assertEquals('Правильное название книги', $book->translation()->get('title', 'ru'));
    }

    /** @test */
    public function it_updates_default_value_for_default_locale(): void
    {
        $book = BookFactory::new()->create(['title' => 'My book']);

        $book->translation()->add('title', 'My english book', $this->app->getLocale());

        self::assertEquals('My english book', $book->getAttribute('title'));
        self::assertEmpty(Translation::all());
    }

    /** @test */
    public function it_throws_an_exception_during_translation_not_translatable_attributes(): void
    {
        $book = BookFactory::new()->create(['title' => 'My book']);

        try {
            $book->translation()->add('version', '5', $this->app->getLocale());
            self::fail('Exception was not thrown for not translatable attribute');
        } catch (AttributeNotTranslatableException $e) {
            self::assertCount(0, Translation::all());
        }
    }
}
