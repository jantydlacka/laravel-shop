<?php

use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\DeleteAction;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('redirects guests to the login page', function () {
    auth()->logout();

    $this->get('/admin/categories')->assertRedirect('/admin/login');
});

it('lists categories', function () {
    $categories = Category::factory()->count(2)->create();

    livewire(ListCategories::class)->assertCanSeeTableRecords($categories);
});

it('blocks deleting a category that has products', function () {
    $category = Category::factory()->has(Product::factory()->count(2))->create();

    livewire(EditCategory::class, ['record' => $category->getRouteKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified('Kategorii nelze smazat');

    expect($category->fresh())->not->toBeNull()
        ->and($category->products()->count())->toBe(2);
});

it('deletes an empty category', function () {
    $category = Category::factory()->create();

    livewire(EditCategory::class, ['record' => $category->getRouteKey()])
        ->callAction(DeleteAction::class);

    expect($category->fresh())->toBeNull();
});
