<?php

namespace App\Livewire;

use App\Cart\Cart;
use App\Cart\QuantityRules;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AddToCart extends Component
{
    public Product $product;

    /** Untyped on purpose: the customer may type anything, validation decides. */
    public mixed $quantity = 1;

    public bool $added = false;

    public function add(Cart $cart): void
    {
        $this->added = false;
        $max = Cart::maxQuantityFor($this->product) - $cart->quantityOf($this->product->id);

        if ($max < 1) {
            $this->addError('quantity', 'Víc kusů už do košíku přidat nejde, máte v něm vše, co je skladem.');

            return;
        }

        $this->validate(['quantity' => QuantityRules::rules($max)], QuantityRules::messages('quantity'));

        $cart->add($this->product, (int) $this->quantity);
        $this->quantity = 1;
        $this->added = true;

        $this->dispatch('cart-updated');
    }

    public function render(): View
    {
        return view('livewire.add-to-cart');
    }
}
