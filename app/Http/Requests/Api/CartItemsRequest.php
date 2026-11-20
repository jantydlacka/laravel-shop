<?php

namespace App\Http\Requests\Api;

use App\Cart\QuantityRules;
use Illuminate\Foundation\Http\FormRequest;

class CartItemsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => QuantityRules::rules((int) config('shop.max_quantity')),
            'items.*.price' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return QuantityRules::messages('items.*.quantity');
    }

    /**
     * @return array<int, array{quantity: int, price: int}>
     */
    public function toCartItems(): array
    {
        $items = [];

        foreach ($this->validated('items') as $item) {
            $items[(int) $item['product_id']] = [
                'quantity' => (int) $item['quantity'],
                'price' => (int) $item['price'],
            ];
        }

        return $items;
    }
}
