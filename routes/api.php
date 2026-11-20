<?php

use App\Http\Controllers\Api\CartCheckController;
use App\Http\Controllers\Api\CatalogController;
use Illuminate\Support\Facades\Route;

Route::get('categories', [CatalogController::class, 'categories']);
Route::get('products', [CatalogController::class, 'products']);
Route::get('products/{product}', [CatalogController::class, 'product']);
Route::post('cart/check', CartCheckController::class);
