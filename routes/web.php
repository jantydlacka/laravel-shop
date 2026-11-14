<?php

use App\Http\Controllers\Shop\CatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('shop.home');
Route::get('/kategorie/{category:slug}', [CatalogController::class, 'category'])->name('shop.category');
Route::get('/produkt/{product:slug}', [CatalogController::class, 'product'])->name('shop.product');
