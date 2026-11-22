<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Testing\TestResponse;

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

/**
 * @return list<string>
 */
function slugsOf(TestResponse $response): array
{
    return collect($response->json('data'))->pluck('slug')->all();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeProduct(string $slug, array $attributes = []): Product
{
    return Product::factory()->create([
        'name' => $slug,
        'slug' => $slug,
        'description' => 'Popis',
        ...$attributes,
    ]);
}

it('paginates 12 per page with meta', function () {
    foreach (range(1, 13) as $i) {
        makeProduct("product-$i", ['created_at' => now()->subMinutes(100 - $i)]);
    }

    $first = $this->getJson('/api/products')->assertOk()->assertJsonCount(12, 'data');
    expect(slugsOf($first)[0])->toBe('product-13')
        ->and(slugsOf($first))->not->toContain('product-1');
    $first->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 12)
        ->assertJsonPath('meta.total', 13)
        ->assertJsonStructure(['data', 'links', 'meta']);

    $second = $this->getJson('/api/products?page=2')->assertOk()->assertJsonCount(1, 'data');
    expect(slugsOf($second))->toBe(['product-1']);
});

it('returns an empty page past the last page', function () {
    makeProduct('only');

    $this->getJson('/api/products?page=5')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.current_page', 5)
        ->assertJsonPath('meta.total', 1);
});

it('orders a category by newest by default', function () {
    $category = Category::factory()->create(['slug' => 'tea']);
    makeProduct('zeleny', ['category_id' => $category->id, 'created_at' => now()->subDay()]);
    makeProduct('assam', ['category_id' => $category->id, 'created_at' => now()->subDays(2)]);
    makeProduct('other');

    expect(slugsOf($this->getJson('/api/products?category=tea')->assertOk()))->toBe(['zeleny', 'assam']);
});

it('searches name and description case-insensitively', function () {
    makeProduct('by-name', ['name' => 'Earl Grey']);
    makeProduct('by-description', ['name' => 'Other', 'description' => 'Black tea with BERGAMOT']);
    makeProduct('miss', ['name' => 'Miss']);

    expect(slugsOf($this->getJson('/api/products?q=earl')->assertOk()))->toBe(['by-name'])
        ->and(slugsOf($this->getJson('/api/products?q=bergamot')->assertOk()))->toBe(['by-description'])
        ->and(slugsOf($this->getJson('/api/products?q=GREY')->assertOk()))->toBe(['by-name']);
});

it('treats % and _ in q literally', function () {
    makeProduct('arabica', ['name' => '100% arabica']);
    makeProduct('plain', ['name' => 'Plain']);
    makeProduct('snake', ['name' => 'snake_case']);
    makeProduct('snakes', ['name' => 'snakexcase']);

    expect(slugsOf($this->getJson('/api/products?q=100%25')->assertOk()))->toBe(['arabica'])
        ->and(slugsOf($this->getJson('/api/products?q=%25')->assertOk()))->toBe(['arabica'])
        ->and(slugsOf($this->getJson('/api/products?q=e_c')->assertOk()))->toBe(['snake']);
});

it('treats quote and backslash in q literally', function () {
    makeProduct('quote', ['name' => "Dunn's tea"]);
    makeProduct('slash', ['name' => 'a\\b blend']);
    makeProduct('plain', ['name' => 'Plain']);

    expect(slugsOf($this->getJson('/api/products?q='.urlencode("n's"))->assertOk()))->toBe(['quote'])
        ->and(slugsOf($this->getJson('/api/products?q='.urlencode('a\\b'))->assertOk()))->toBe(['slash'])
        ->and(slugsOf($this->getJson('/api/products?q='.urlencode('\\'))->assertOk()))->toBe(['slash']);
});

it('ignores a whitespace-only q', function () {
    makeProduct('one');
    makeProduct('two');

    $this->getJson('/api/products?q='.urlencode('   '))->assertOk()->assertJsonCount(2, 'data');
});

it('trims q before searching', function () {
    makeProduct('match', ['name' => 'Matcha']);
    makeProduct('miss', ['name' => 'Miss']);

    expect(slugsOf($this->getJson('/api/products?q='.urlencode('  matcha  '))->assertOk()))->toBe(['match']);
});

it('sorts by each sort value with id as tie-breaker', function (string $sort, array $expected) {
    makeProduct('a', ['name' => 'Beta', 'price' => 200, 'created_at' => '2026-01-01 10:00:00']);
    makeProduct('b', ['name' => 'Alfa', 'price' => 300, 'created_at' => '2026-01-02 10:00:00']);
    makeProduct('c', ['name' => 'Beta', 'price' => 200, 'created_at' => '2026-01-01 10:00:00']);
    makeProduct('d', ['name' => 'Alfa', 'price' => 300, 'created_at' => '2026-01-02 10:00:00']);

    expect(slugsOf($this->getJson("/api/products?sort=$sort")->assertOk()))->toBe($expected);
})->with([
    'newest' => ['newest', ['d', 'b', 'c', 'a']],
    'name' => ['name', ['b', 'd', 'a', 'c']],
    'price_asc' => ['price_asc', ['a', 'c', 'b', 'd']],
    'price_desc' => ['price_desc', ['d', 'b', 'c', 'a']],
]);

it('pages deterministically when sort keys tie', function (string $sort) {
    foreach (range(1, 13) as $i) {
        makeProduct("p-$i", ['name' => 'Same', 'price' => 1000, 'created_at' => '2026-01-01 10:00:00']);
    }

    $all = [
        ...slugsOf($this->getJson("/api/products?sort=$sort&page=1")->assertOk()),
        ...slugsOf($this->getJson("/api/products?sort=$sort&page=2")->assertOk()),
    ];

    expect($all)->toHaveCount(13)->and(array_unique($all))->toHaveCount(13);
})->with(['newest', 'name', 'price_asc', 'price_desc']);

it('filters in_stock', function () {
    makeProduct('in', ['stock' => 3]);
    makeProduct('out', ['stock' => 0]);

    expect(slugsOf($this->getJson('/api/products?in_stock=1')->assertOk()))->toBe(['in'])
        ->and(slugsOf($this->getJson('/api/products?in_stock=0')->assertOk()))->toEqualCanonicalizing(['in', 'out']);
});

it('combines category, q, in_stock and sort', function () {
    $tea = Category::factory()->create(['slug' => 'tea']);
    $coffee = Category::factory()->create(['slug' => 'coffee']);
    makeProduct('tea-cheap', ['category_id' => $tea->id, 'name' => 'Green tea', 'price' => 100]);
    makeProduct('tea-dear', ['category_id' => $tea->id, 'name' => 'Black tea', 'price' => 900]);
    makeProduct('tea-out', ['category_id' => $tea->id, 'name' => 'Rare tea', 'price' => 500, 'stock' => 0]);
    makeProduct('tea-other', ['category_id' => $tea->id, 'name' => 'Rooibos', 'price' => 50]);
    makeProduct('coffee-tea', ['category_id' => $coffee->id, 'name' => 'Tea-like coffee', 'price' => 700]);

    $response = $this->getJson('/api/products?category=tea&q=tea&in_stock=1&sort=price_desc')->assertOk();

    expect(slugsOf($response))->toBe(['tea-dear', 'tea-cheap']);
    $response->assertJsonPath('meta.total', 2);
});

it('rejects invalid parameters with 422', function (string $query, string $key) {
    $this->getJson("/api/products?$query")
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors'])
        ->assertJsonValidationErrors($key);
})->with([
    'unknown sort' => ['sort=xyz', 'sort'],
    'page zero' => ['page=0', 'page'],
    'page above the limit' => ['page=10001', 'page'],
    'page beyond int range' => ['page=9223372036854775807', 'page'],
    'page not a number' => ['page=abc', 'page'],
    'in_stock not boolean' => ['in_stock=abc', 'in_stock'],
    'q as array' => ['q[]=x', 'q'],
    'q too long' => ['q='.str_repeat('a', 101), 'q'],
    'q too long in characters' => ['q='.str_repeat('č', 101), 'q'],
]);

it('accepts q of 100 characters, also non-ASCII, and trims before counting', function (string $q) {
    $this->getJson('/api/products?q='.urlencode($q))->assertOk();
})->with([
    'ascii' => [str_repeat('a', 100)],
    'czech' => [str_repeat('č', 100)],
    'padded' => ['  '.str_repeat('a', 100).'  '],
]);

it('accepts the highest page and returns it empty', function () {
    makeProduct('one');

    $this->getJson('/api/products?page=10000')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.current_page', 10000);
});

it('keeps unknown parameters out of the pagination links', function () {
    foreach (range(1, 13) as $i) {
        makeProduct("p-$i");
    }

    $response = $this->getJson('/api/products?foo=bar&q=p&sort=name&in_stock=1')->assertOk();

    expect($response->json('links.next'))->toContain('q=p', 'sort=name', 'in_stock=1')->not->toContain('foo')
        ->and($response->json('links.first'))->not->toContain('foo');
});

it('keeps the category in the pagination links', function () {
    $tea = Category::factory()->create(['slug' => 'tea']);
    foreach (range(1, 13) as $i) {
        makeProduct("t-$i", ['category_id' => $tea->id]);
    }

    $response = $this->getJson('/api/products?category=tea&q=t&sort=name&in_stock=1')->assertOk();

    expect($response->json('links.next'))->toContain('category=tea', 'q=t', 'sort=name', 'in_stock=1')
        ->and($response->json('links.first'))->toContain('category=tea');
});

it('accepts in_stock values', function (string $value, int $status) {
    makeProduct('in', ['stock' => 3]);
    makeProduct('out', ['stock' => 0]);

    $this->getJson("/api/products?in_stock=$value")->assertStatus($status);
})->with([
    'one' => ['1', 200],
    'zero' => ['0', 200],
    'true' => ['true', 422],
    'false' => ['false', 422],
]);

it('matches diacritics as the database collation does', function (string $q, array $expected) {
    makeProduct('caj', ['name' => 'Čaj']);
    makeProduct('kava', ['name' => 'Káva']);
    makeProduct('mlynek', ['name' => 'Ruční mlýnek']);
    makeProduct('chut', ['name' => 'Chuť']);

    expect(slugsOf($this->getJson('/api/products?q='.urlencode($q))->assertOk()))->toBe($expected);
})->with([
    'ascii finds accented' => ['caj', ['caj']],
    'uppercase ascii' => ['CAJ', ['caj']],
    'accented' => ['čaj', ['caj']],
    'kava' => ['kava', ['kava']],
    'mlynek' => ['mlynek', ['mlynek']],
    'ch is not a separate letter' => ['chut', ['chut']],
    'ch as plain letters' => ['ch', ['chut']],
]);

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
