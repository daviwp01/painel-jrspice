<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        $country = Country::firstOrCreate(['name' => 'Brasil']);

        return Product::create(array_merge([
            'name' => 'Alho em pó A',
            'name_en' => 'Garlic powder A',
            'name_es' => 'Ajo en polvo A',
            'country_id' => $country->id,
        ], $overrides));
    }

    public function test_display_name_follows_locale_without_touching_canonical_name(): void
    {
        $product = $this->makeProduct();

        app()->setLocale('en');
        $this->assertSame('Garlic powder A', $product->displayName());

        app()->setLocale('es');
        $this->assertSame('Ajo en polvo A', $product->displayName());

        app()->setLocale('pt_BR');
        $this->assertSame('Alho em pó A', $product->displayName());

        // Canonical identifier never changes
        $this->assertSame('Alho em pó A', $product->name);
    }

    public function test_display_name_falls_back_to_name_when_translation_missing(): void
    {
        $product = $this->makeProduct(['name_en' => null, 'name_es' => null]);

        app()->setLocale('en');
        $this->assertSame('Alho em pó A', $product->displayName());
    }

    public function test_search_and_price_queries_still_use_canonical_name(): void
    {
        $product = $this->makeProduct();
        $country = $product->country;

        ProductPrice::create([
            'product_id' => $product->id,
            'country_id' => $country->id,
            'date' => now()->toDateString(),
            'price' => 100,
        ]);

        // Search by canonical PT name (legacy behaviour)
        $found = Product::where('name', 'like', '%Alho em%')->first();
        $this->assertNotNull($found);
        $this->assertSame($product->id, $found->id);

        // Searching by translation must NOT match (name column only)
        $byEn = Product::where('name', 'like', '%Garlic powder%')->first();
        $this->assertNull($byEn);

        // Price aggregation unchanged
        $sum = ProductPrice::where('product_id', $product->id)->sum('price');
        $this->assertSame(100.0, (float) $sum);
    }

    public function test_admin_can_store_product_translations(): void
    {
        $country = Country::firstOrCreate(['name' => 'Índia']);

        $response = $this->actingAs($this->masterUser())->post(route('admin.data.products.store'), [
            'name' => 'Canela em pó',
            'name_en' => 'Cinnamon powder',
            'name_es' => 'Canela en polvo',
            'country_id' => $country->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'name' => 'Canela em pó',
            'name_en' => 'Cinnamon powder',
            'name_es' => 'Canela en polvo',
        ]);
    }

    public function test_import_translation_columns_fill_product_fields(): void
    {
        $country = Country::firstOrCreate(['name' => 'Peru']);
        $product = Product::create([
            'name' => 'Pimenta-do-Reino',
            'country_id' => $country->id,
        ]);

        // Simulates updateOrCreate payload built by ProcessDataImport when
        // optional PRODUTO_EN / PRODUTO_ES headers are present.
        $product->fill([
            'name_en' => 'Black pepper',
            'name_es' => 'Pimienta negra',
        ])->save();

        $this->assertDatabaseHas('products', [
            'name' => 'Pimenta-do-Reino',
            'name_en' => 'Black pepper',
            'name_es' => 'Pimienta negra',
        ]);
    }

    private function masterUser(): \App\Models\User
    {
        return \App\Models\User::factory()->create(['is_master' => true]);
    }
}
