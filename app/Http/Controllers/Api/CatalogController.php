<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductIndexRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->orderBy('position')->orderBy('name')->get(),
        );
    }

    public function products(ProductIndexRequest $request): AnonymousResourceCollection
    {
        $slug = $request->query('category');

        $query = is_string($slug) && $slug !== ''
            ? Category::query()->where('slug', $slug)->firstOrFail()->products()->getQuery()
            : Product::query();

        $query->available()->with('category');

        if (($search = $request->search()) !== null) {
            $like = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(fn (Builder $match) => $match
                ->whereRaw("name like ? escape '!'", [$like])
                ->orWhereRaw("description like ? escape '!'", [$like]));
        }

        if ($request->onlyInStock()) {
            $query->where('stock', '>', 0);
        }

        match ($request->sort()) {
            'name' => $query->orderBy('name')->orderBy('id'),
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return ProductResource::collection($query->paginate(12)->appends($request->appendedQuery()));
    }

    public function product(Product $product): ProductResource
    {
        abort_unless($product->is_available, 404);

        return new ProductResource($product->load('category'));
    }
}
