<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class CatalogController extends Controller
{
    public function home(): View
    {
        return view('shop.home', [
            'categories' => Category::query()->orderBy('position')->orderBy('name')->get(),
            'products' => Product::available()->with('category')->latest()->take(12)->get(),
        ]);
    }

    public function category(Category $category): View
    {
        return view('shop.category', [
            'category' => $category,
            'categories' => Category::query()->orderBy('position')->orderBy('name')->get(),
            'products' => $category->products()->available()->orderBy('name')->get(),
        ]);
    }

    public function product(Product $product): View
    {
        abort_unless($product->is_available, 404);

        return view('shop.product', [
            'product' => $product->load('category'),
        ]);
    }
}
