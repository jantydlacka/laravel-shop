<?php

namespace App\Http\Resources;

use App\Cart\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'stock' => $this->stock,
            'max_quantity' => Cart::maxQuantityFor($this->resource),
            'image_url' => $this->image_url,
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
