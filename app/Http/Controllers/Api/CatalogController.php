<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->orderBy('position')->orderBy('name')->get(),
        );
    }

    public function products(Request $request): AnonymousResourceCollection
    {
        $request->validate(['category' => ['nullable', 'string']]);

        $slug = $request->query('category');

        if (is_string($slug)) {
            $category = Category::query()->where('slug', $slug)->firstOrFail();
            $products = $category->products()->available()->with('category')->orderBy('name')->get();
        } else {
            $products = Product::available()->with('category')->latest()->take(12)->get();
        }

        return ProductResource::collection($products);
    }

    public function product(Product $product): ProductResource
    {
        abort_unless($product->is_available, 404);

        return new ProductResource($product->load('category'));
    }
}
