<?php

use App\Models\Category;
use App\Models\Product;

it('lists categories ordered by position and name', function () {
    Category::factory()->create(['name' => 'Beta', 'slug' => 'beta', 'position' => 2]);
    Category::factory()->create(['name' => 'Zeta', 'slug' => 'zeta', 'position' => 1]);
    Category::factory()->create(['name' => 'Alfa', 'slug' => 'alfa', 'position' => 1]);

    $this->getJson('/api/categories')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['slug' => 'alfa', 'name' => 'Alfa'],
            ['slug' => 'zeta', 'name' => 'Zeta'],
            ['slug' => 'beta', 'name' => 'Beta'],
        ]]);
});

it('lists the 12 newest available products', function () {
    $category = Category::factory()->create();
    foreach (range(1, 13) as $i) {
        Product::factory()->for($category)->create([
            'name' => "Product $i",
            'slug' => "product-$i",
            'created_at' => now()->subMinutes(100 - $i),
        ]);
    }

    $response = $this->getJson('/api/products')->assertOk()->assertJsonCount(12, 'data');

    expect(collect($response->json('data'))->pluck('slug')->first())->toBe('product-13')
        ->and(collect($response->json('data'))->pluck('slug')->all())->not->toContain('product-1');
});

it('filters products by category ordered by name', function () {
    $category = Category::factory()->create(['slug' => 'tea']);
    Product::factory()->for($category)->create(['name' => 'Zelený', 'slug' => 'zeleny']);
    Product::factory()->for($category)->create(['name' => 'Assam', 'slug' => 'assam']);
    Product::factory()->create(['name' => 'Other']);

    $response = $this->getJson('/api/products?category=tea')->assertOk();

    expect(collect($response->json('data'))->pluck('slug')->all())->toBe(['assam', 'zeleny']);
});

it('returns 404 for an unknown category', function () {
    $this->getJson('/api/products?category=nope')->assertNotFound();
});

it('shows a product detail with the documented shape', function () {
    config(['shop.max_quantity' => 5]);
    $category = Category::factory()->create(['slug' => 'tea', 'name' => 'Čaj']);
    $product = Product::factory()->for($category)->create([
        'slug' => 'assam', 'name' => 'Assam', 'price' => 12900, 'stock' => 3,
        'description' => 'Popis', 'image_path' => 'products/assam.jpg',
    ]);

    $this->getJson('/api/products/assam')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $product->id,
            'slug' => 'assam',
            'name' => 'Assam',
            'description' => 'Popis',
            'price' => 12900,
            'stock' => 3,
            'max_quantity' => 3,
            'image_url' => url('/storage/products/assam.jpg'),
            'category' => ['slug' => 'tea', 'name' => 'Čaj'],
        ]]);
});

it('returns a null image_url and caps max_quantity by config', function () {
    config(['shop.max_quantity' => 5]);
    Product::factory()->create(['slug' => 'plain', 'stock' => 50, 'image_path' => null]);

    $this->getJson('/api/products/plain')
        ->assertOk()
        ->assertJsonPath('data.image_url', null)
        ->assertJsonPath('data.max_quantity', 5);
});

it('returns 404 for an unavailable product detail', function () {
    Product::factory()->unavailable()->create(['slug' => 'hidden']);

    $this->getJson('/api/products/hidden')->assertNotFound();
});

it('hides unavailable products from listings', function () {
    $category = Category::factory()->create(['slug' => 'tea']);
    Product::factory()->for($category)->create(['slug' => 'shown']);
    Product::factory()->for($category)->unavailable()->create(['slug' => 'hidden']);

    foreach (['/api/products', '/api/products?category=tea'] as $url) {
        $slugs = collect($this->getJson($url)->assertOk()->json('data'))->pluck('slug')->all();
        expect($slugs)->toBe(['shown']);
    }
});

it('rejects a category sent as an array', function () {
    $this->getJson('/api/products?category[]=x')
        ->assertStatus(422)
        ->assertJsonValidationErrors('category');
});

it('treats an empty category like no category', function () {
    $category = Category::factory()->create(['slug' => 'tea']);
    Product::factory()->for($category)->create(['slug' => 'assam']);

    $this->getJson('/api/products?category=')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'assam');
});
