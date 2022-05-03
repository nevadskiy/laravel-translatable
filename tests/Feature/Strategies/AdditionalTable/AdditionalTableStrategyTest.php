<?php

namespace Nevadskiy\Translatable\Tests\Feature\Strategies\AdditionalTable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Nevadskiy\Translatable\Strategies\AdditionalTable\HasTranslations;
use Nevadskiy\Translatable\Tests\Support\Factories\ProductFactory;
use Nevadskiy\Translatable\Tests\Support\Models\Product;
use Nevadskiy\Translatable\Tests\TestCase;

class AdditionalTableStrategyTest extends TestCase
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
    public function it_can_create_models_in_custom_locale_correctly(): void
    {
        $this->app->setLocale('uk');

        $product = new Product();
        $product->title = 'Свитер с оленями';
        $product->description = 'Теплый зимний свитер';
        $product->save();

        $this->assertDatabaseHas('products', [
            'title' => 'Свитер с оленями',
            'description' => 'Теплый зимний свитер',
        ]);
        $this->assertDatabaseCount('product_translations', 0);
    }

    /** @test */
    public function it_stores_translatable_model_correctly(): void
    {
        ProductFactory::new()->create([
            'title' => 'Reindeer Sweater',
            'description' => 'Warm winter sweater',
        ]);

        $this->assertDatabaseHas('products', [
            'title' => 'Reindeer Sweater',
            'description' => 'Warm winter sweater',
        ]);
        $this->assertDatabaseCount('product_translations', 0);
    }

    /** @test */
    public function it_stores_translations_in_additional_table(): void
    {
        $product = ProductFactory::new()->create([
            'title' => 'Reindeer Sweater',
            'description' => 'Warm winter sweater',
        ]);

        $product->translator()->set('title', 'Свитер с оленями', 'uk');
        $product->translator()->set('description', 'Теплый зимний свитер', 'uk');
        $product->translator()->save();

        $this->assertDatabaseCount('product_translations', 1);
        $this->assertDatabaseHas('product_translations', [
            'title' => 'Свитер с оленями',
            'description' => 'Теплый зимний свитер',
            'locale' => 'uk',
        ]);
    }

    /** @test */
    public function it_does_not_store_translations_without_save_call(): void
    {
        $product = ProductFactory::new()->create([
            'title' => 'Reindeer Sweater',
            'description' => 'Warm winter sweater',
        ]);

        $product->translator()->set('title', 'Свитер с оленями', 'uk');
        $product->translator()->set('description', 'Теплый зимний свитер', 'uk');

        $this->assertDatabaseCount('product_translations', 0);
    }

    /** @test */
    public function it_does_not_override_translations_on_double_save_call(): void
    {
        $product = ProductFactory::new()->create([
            'title' => 'Reindeer Sweater',
            'description' => 'Warm winter sweater',
        ]);

        $product->translator()->set('title', 'Свитер с оленями', 'uk');
        $product->translator()->set('description', 'Теплый зимний свитер', 'uk');
        $product->translator()->save();

        DB::enableQueryLog();

        $product->translator()->save();

        self::assertEmpty(DB::connection()->getQueryLog());
        $this->assertDatabaseCount('product_translations', 1);
    }

    /** @test */
    public function it_retrieves_translation_from_additional_table(): void
    {
        $product = ProductFactory::new()->create(['title' => 'Reindeer Sweater']);

        $product->translator()->set('title', 'Свитер с оленями', 'uk');

        self::assertEquals('Свитер с оленями', $product->translator()->get('title', 'uk'));
    }

    /** @test */
    public function it_automatically_stores_translation_for_translatable_attribute_using_current_locale(): void
    {
        $product = ProductFactory::new()->create(['title' => 'Reindeer Sweater']);

        $this->app->setLocale('uk');

        $product->title = 'Свитер с оленями';
        $product->save();

        $this->assertDatabaseCount('product_translations', 1);
        $this->assertDatabaseHas('product_translations', [
            'title' => 'Свитер с оленями',
            'locale' => 'uk',
        ]);
    }

    /** @test */
    public function it_automatically_retrieves_translation_for_translatable_attribute_using_current_locale(): void
    {
        $product = ProductFactory::new()->create(['title' => 'Reindeer Sweater']);

        $product->translator()->set('title', 'Свитер с оленями', 'uk');

        $this->app->setLocale('uk');

        self::assertEquals('Свитер с оленями', $product->title);
    }

    /** @test */
    public function it_retrieves_translations_without_additional_queries_when_they_are_preloaded(): void
    {
        $product1 = ProductFactory::new()->create(['title' => 'Reindeer Sweater']);
        $product2 = ProductFactory::new()->create(['title' => 'Sony PlayStation']);
        $product3 = ProductFactory::new()->create(['title' => 'LG Boiler']);

        $product1->translator()->add('title', 'Свитер с оленями', 'uk');
        $product2->translator()->add('title', 'Sony ИгроваяСтанция', 'uk');
        $product3->translator()->add('title', 'LG чайник', 'uk');

        $products = Product::query()->withoutGlobalScopes()->with('translations')->get();

        $this->app->setLocale('uk');

        DB::enableQueryLog();

        self::assertEquals('Свитер с оленями', $products[0]->title);
        self::assertEquals('Sony ИгроваяСтанция', $products[1]->title);
        self::assertEquals('LG чайник', $products[2]->title);

        self::assertEmpty(DB::getQueryLog());
    }

    /** @test */
    public function it_automatically_eager_loads_translations_for_current_locale(): void
    {
        $product1 = ProductFactory::new()->create(['title' => 'Reindeer Sweater']);
        $product2 = ProductFactory::new()->create(['title' => 'Sony PlayStation']);
        $product3 = ProductFactory::new()->create(['title' => 'LG Boiler']);

        $product1->translator()->add('title', 'Свитер с оленями', 'uk');
        $product2->translator()->add('title', 'Sony ИгроваяСтанция', 'uk');
        $product3->translator()->add('title', 'LG чайник', 'uk');

        $this->app->setLocale('uk');

        $products = Product::query()->get();

        DB::enableQueryLog();

        self::assertEquals('Свитер с оленями', $products[0]->title);
        self::assertEquals('Sony ИгроваяСтанция', $products[1]->title);
        self::assertEquals('LG чайник', $products[2]->title);

        self::assertEmpty(DB::getQueryLog());
    }

    // TODO: REWORK ABOVE
    // TODO: REWORK ABOVE
    // TODO: REWORK ABOVE

    /** @test */
    public function it_stores_translations_using_additional_table_strategy(): void
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
    public function it_retrieves_translations_using_single_table_strategy(): void
    {
        $book = new Book();
        $book->translator()->set('title', 'Amazing birds', 'en');
        $book->translator()->set('title', 'Дивовижні птахи', 'uk');
        $book->translator()->set('description', 'This book will help you discover all the secrets of birds', 'en');
        $book->translator()->set('description', 'Ця книга допоможе тобі вивідати всі пташині таємниці', 'uk');
        $book->save();

        self::assertEquals('Amazing birds', $book->translator()->get('title', 'en'));
        self::assertEquals('Дивовижні птахи', $book->translator()->get('title', 'uk'));
        self::assertEquals('This book will help you discover all the secrets of birds', $book->translator()->get('description', 'en'));
        self::assertEquals('Ця книга допоможе тобі вивідати всі пташині таємниці', $book->translator()->get('description', 'uk'));
    }

    /** @test */
    public function it_stores_translations_using_attribute_interceptors_on_single_table_strategy(): void
    {
        $book = new Book();

        $this->app->setLocale('en');
        $book->title = 'Amazing birds';

        $this->app->setLocale('uk');
        $book->title = 'Дивовижні птахи';

        $book->save();

        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseCount('translations', 2);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => $book->getMorphClass(),
            'translatable_id' => $book->getKey(),
            'translatable_attribute' => 'title',
            'locale' => 'en',
            'value' => 'Amazing birds'
        ]);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => $book->getMorphClass(),
            'translatable_id' => $book->getKey(),
            'translatable_attribute' => 'title',
            'locale' => 'uk',
            'value' => 'Дивовижні птахи'
        ]);
    }

    /** @test */
    public function it_retrieves_translations_using_attribute_interceptors_on_single_table_strategy(): void
    {
        $book = new Book();
        $book->translator()->set('title', 'Amazing birds', 'en');
        $book->translator()->set('title', 'Дивовижні птахи', 'uk');
        $book->save();

        $this->app->setLocale('en');
        self::assertEquals('Amazing birds', $book->title);

        $this->app->setLocale('uk');
        self::assertEquals('Дивовижні птахи', $book->title);
    }

    /** @test */
    public function it_fills_translatable_attributes_with_nulls_when_translations_are_missing(): void
    {
        $book = new Book();
        $book->save();

        $array = $book->toArray();

        self::assertNull($array['title']);
        self::assertNull($array['description']);
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
            self::fail('Exception was not thrown for not translatable attribute');
        } catch (AttributeNotTranslatableException $e) {
            $this->assertDatabaseCount('translations', 1);
        }
    }

    /** @test */
    public function it_returns_fallback_value_if_translation_is_missing(): void
    {
        $book = new Book();
        $book->title = 'Atlas of animals';
        $book->save();

        self::assertEquals('Atlas of animals', $book->translator()->get('title', 'uk'));
    }

    /** @test */
    public function it_returns_null_if_translation_is_nullable(): void
    {
        $book = new Book();
        $book->translator()->set('title', 'Atlas of animals', 'en');
        $book->translator()->set('title', null, 'uk');
        $book->save();

        self::assertNull($book->translator()->get('title', 'uk'));
    }

    /** @test */
    public function it_overrides_previous_translations(): void
    {
        $book = new Book();
        $book->translator()->set('title', 'The world around us. Wild animals', 'en');
        $book->translator()->set('title', 'Світ навколо нас', 'uk');
        $book->save();

        self::assertEquals('Світ навколо нас', $book->translator()->get('title', 'uk'));

        $book->translator()->add('title', 'Світ навколо нас. Дикі тварини', 'uk');

        self::assertEquals('Світ навколо нас. Дикі тварини', $book->translator()->get('title', 'uk'));
        $this->assertDatabaseCount('translations', 2);
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

        DB::connection()->enableQueryLog();

        $book->save();

        self::assertEmpty(DB::connection()->getQueryLog());
        $this->assertDatabaseCount('translations', 2);
    }

    /** @test */
    public function it_does_not_duplicate_translations(): void
    {
        $book = new Book();
        $book->title = 'The world around us';
        $book->save();

        $book->translator()->add('title', 'Світ навколо нас', 'uk');
        $book->translator()->add('title', 'Світ навколо нас', 'uk');

        $this->assertDatabaseCount('translations', 2);
    }

    /** @test */
    public function it_performs_only_one_query_to_retrieve_translation_for_same_attribute_and_locale(): void
    {
        $book = new Book();
        $book->translator()->add('title', 'The world around us', 'en');
        $book->translator()->add('title', 'Світ навколо нас', 'uk');
        $book->save();

        $book = $book->fresh();

        DB::connection()->enableQueryLog();

        $book->translator()->get('title', 'uk');
        $book->translator()->get('title', 'uk');
        $book->translator()->get('title', 'uk');

        self::assertCount(1, DB::connection()->getQueryLog());
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('books');
        $this->schema()->drop('book_translations');
        parent::tearDown();
    }
}

/**
 * @property string title
 * @property string description
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