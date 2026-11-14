<?php

namespace App\Cart;

use App\Models\Product;

final readonly class CartLine
{
    /**
     * @param  int  $price  unit price in hellers the customer saw when adding
     */
    public function __construct(
        public Product $product,
        public int $quantity,
        public int $price,
    ) {}

    public function lineTotal(): int
    {
        return $this->price * $this->quantity;
    }
}
