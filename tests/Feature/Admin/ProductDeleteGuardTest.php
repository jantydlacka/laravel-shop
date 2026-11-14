<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\DeleteAction;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('blocks deleting a product that is in an order', function () {
    $product = Product::factory()->create();
    Order::factory()->withItem($product)->create();

    livewire(EditProduct::class, ['record' => $product->getRouteKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified('Produkt nelze smazat');

    expect($product->fresh())->not->toBeNull();
});

it('deletes a product that was never ordered', function () {
    $product = Product::factory()->create();

    livewire(EditProduct::class, ['record' => $product->getRouteKey()])
        ->callAction(DeleteAction::class);

    expect($product->fresh())->toBeNull();
});
