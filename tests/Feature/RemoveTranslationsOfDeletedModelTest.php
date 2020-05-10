<?php

namespace Nevadskiy\Translatable\Tests\Feature;

use Nevadskiy\Translatable\Models\Translation;
use Nevadskiy\Translatable\Tests\Support\Factories\BookFactory;
use Nevadskiy\Translatable\Tests\Support\Factories\PostFactory;
use Nevadskiy\Translatable\Tests\TestCase;

class RemoveTranslationsOfDeletedModelTest extends TestCase
{
    /** @test */
    public function it_removes_translations_when_model_is_deleted(): void
    {
        $book1 = BookFactory::new()->create();
        $removedTranslations = $book1->translateMany(['title' => 'Птицы', 'description' => 'Книга о птицах'], 'ru');

        $book2 = BookFactory::new()->create();
        $translations = $book2->translateMany(['title' => 'Дельфины', 'description' => 'Книга о дельфинах'], 'ru');

        $this->assertCount(4, Translation::all());

        $book1->delete();

        $this->assertCount(2, Translation::all());
        $this->assertNotNull($translations[0]->fresh());
        $this->assertNotNull($translations[1]->fresh());
        $this->assertNull($removedTranslations[0]->fresh());
        $this->assertNull($removedTranslations[1]->fresh());
    }

    /** @test */
    public function it_does_not_remove_translations_when_model_is_soft_deleted(): void
    {
        $post1 = PostFactory::new()->create();
        $post1->translate('body', 'Удаленный пост', 'ru');

        $this->assertCount(1, Translation::all());

        $post1->delete();

        $this->assertCount(1, Translation::all());
    }

    /** @test */
    public function it_removes_translations_of_force_deleted_models(): void
    {
        $post1 = PostFactory::new()->create();
        $post1->translate('body', 'Удаленный пост', 'ru');

        $this->assertCount(1, Translation::all());

        $post1->forceDelete();

        $this->assertCount(0, Translation::all());
    }
}