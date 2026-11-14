<?php

use App\Http\Controllers\Shop\CatalogController;
use App\Livewire\CartPage;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('shop.home');
Route::get('/kategorie/{category:slug}', [CatalogController::class, 'category'])->name('shop.category');
Route::get('/produkt/{product:slug}', [CatalogController::class, 'product'])->name('shop.product');
Route::get('/kosik', CartPage::class)->name('shop.cart');
