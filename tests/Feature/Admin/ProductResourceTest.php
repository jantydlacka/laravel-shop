<?php

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('creates a product with the price entered in crowns', function (string $input, int $stored) {
    $category = Category::factory()->create();

    livewire(CreateProduct::class)
        ->fillForm([
            'name' => 'Moka konvička',
            'slug' => 'moka-konvicka',
            'category_id' => $category->id,
            'description' => 'Na tři šálky.',
            'price' => $input,
            'stock' => 5,
            'is_available' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::sole()->price)->toBe($stored);
})->with([
    'comma decimals' => ['1299,50', 129950],
    'dot decimals' => ['1299.5', 129950],
    'whole crowns' => ['1299', 129900],
    'one heller' => ['0,01', 1],
    'highest price' => ['1000000', 100_000_000],
]);

it('rejects an invalid price', function (string $input) {
    livewire(CreateProduct::class)
        ->fillForm([
            'name' => 'Moka konvička',
            'slug' => 'moka-konvicka',
            'category_id' => Category::factory()->create()->id,
            'description' => 'Na tři šálky.',
            'price' => $input,
            'stock' => 5,
        ])
        ->call('create')
        ->assertHasFormErrors(['price']);

    expect(Product::count())->toBe(0);
})->with(['-1', 'abc', '12,345', '', 'too many digits' => '99999999999999999']);

it('rejects a price above the limit with a Czech message', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name' => 'Moka konvička',
            'slug' => 'moka-konvicka',
            'category_id' => Category::factory()->create()->id,
            'description' => 'Na tři šálky.',
            'price' => '1000000,01',
            'stock' => 5,
        ])
        ->call('create')
        ->assertHasFormErrors(['price'])
        ->assertSee('Cena může být nejvýš '.str_replace(' ', "\u{a0}", '1 000 000 Kč').'.');

    expect(Product::count())->toBe(0);
});

it('rejects stock above the limit', function () {
    livewire(CreateProduct::class)
        ->fillForm([
            'name' => 'Moka konvička',
            'slug' => 'moka-konvicka',
            'category_id' => Category::factory()->create()->id,
            'description' => 'Na tři šálky.',
            'price' => '899',
            'stock' => 100_001,
        ])
        ->call('create')
        ->assertHasFormErrors(['stock' => 'max']);

    expect(Product::count())->toBe(0);
});

it('shows form errors in Czech', function () {
    livewire(CreateProduct::class)
        ->call('create')
        ->assertHasFormErrors(['name' => 'required'])
        ->assertSee('Pole název je povinné.');
});

it('shows the stored price in crowns when editing', function () {
    $product = Product::factory()->create(['price' => 129950]);

    livewire(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertSchemaStateSet(['price' => '1299,50']);
});

it('lists products with formatted prices', function () {
    $product = Product::factory()->create(['price' => 129950]);

    livewire(ListProducts::class)
        ->assertCanSeeTableRecords([$product])
        ->assertSee(str_replace(' ', "\u{a0}", '1 299,50 Kč'));
});

it('keeps a seeded image when the product is saved unchanged', function () {
    Storage::fake('public');
    $this->seed(DemoCatalogSeeder::class);
    $product = Product::where('slug', 'rooibos-vanilka')->sole();
    $imagePath = $product->image_path;

    livewire(EditProduct::class, ['record' => $product->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->refresh()->image_path)->toBe($imagePath)->not->toBeNull();
});

it('keeps stock sold while the form was open when the admin did not touch the stock', function () {
    $product = Product::factory()->create(['stock' => 10, 'description' => 'Starý popis']);
    $form = livewire(EditProduct::class, ['record' => $product->getRouteKey()]);
    $product->decrement('stock', 3);

    $form->fillForm(['description' => 'Opravený popis'])->call('save')
        ->assertHasNoFormErrors()
        ->assertSchemaStateSet(['stock' => 7]);

    expect($product->refresh())->stock->toBe(7)->description->toBe('Opravený popis');
});

it('refuses a stock change when the stock moved since the form was loaded', function () {
    $product = Product::factory()->create(['stock' => 10, 'description' => 'Starý popis']);
    $form = livewire(EditProduct::class, ['record' => $product->getRouteKey()]);
    $product->decrement('stock', 8);

    $form->fillForm(['stock' => 5, 'description' => 'Opravený popis'])->call('save')
        ->assertNotified('Sklad se mezitím změnil')
        ->assertSchemaStateSet(['stock' => 2]);

    expect($product->refresh())->stock->toBe(2)->description->toBe('Starý popis');

    $form->fillForm(['stock' => 5])->call('save')->assertHasNoFormErrors();

    expect($product->refresh()->stock)->toBe(5);
});

it('saves a stock change when nothing else touched the stock', function () {
    $product = Product::factory()->create(['stock' => 10]);

    livewire(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['stock' => 12])->call('save')->assertHasNoFormErrors();

    expect($product->refresh()->stock)->toBe(12);
});

it('does not write the stock back on a second save without reloading', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $form = livewire(EditProduct::class, ['record' => $product->getRouteKey()]);
    $form->fillForm(['stock' => 12])->call('save')->assertHasNoFormErrors();
    $product->decrement('stock', 2);

    $form->fillForm(['description' => 'Druhá úprava'])->call('save')->assertHasNoFormErrors();

    expect($product->refresh()->stock)->toBe(10);
});

it('accepts a second stock change after its own save without reloading', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $form = livewire(EditProduct::class, ['record' => $product->getRouteKey()]);
    $form->fillForm(['stock' => 12])->call('save')->assertHasNoFormErrors();

    $form->fillForm(['stock' => 15])->call('save')->assertHasNoFormErrors();

    expect($product->refresh()->stock)->toBe(15);
});
