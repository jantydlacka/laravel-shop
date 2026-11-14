<?php

namespace App\Livewire;

use App\Cart\Cart;
use App\Cart\QuantityRules;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.shop')]
#[Title('Košík')]
class CartPage extends Component
{
    /** @var array<int, mixed> product id => quantity as typed */
    public array $quantities = [];

    /** @var list<string> */
    public array $notices = [];

    public function mount(Cart $cart): void
    {
        $this->notices = $cart->refresh();
        $this->syncQuantities($cart);
    }

    public function updatedQuantities(mixed $value, string $key): void
    {
        $cart = app(Cart::class);
        $line = $cart->lines()->first(fn ($line) => $line->product->id === (int) $key);

        if ($line === null) {
            $this->syncQuantities($cart);

            return;
        }

        $field = "quantities.{$key}";
        $this->validate(
            [$field => QuantityRules::rules(Cart::maxQuantityFor($line->product))],
            QuantityRules::messages($field),
        );

        $cart->update($line->product->id, (int) $value);
        $this->dispatch('cart-updated');
    }

    public function remove(int $productId, Cart $cart): void
    {
        $cart->remove($productId);
        $this->syncQuantities($cart);
        $this->dispatch('cart-updated');
    }

    public function render(Cart $cart): View
    {
        return view('livewire.cart-page', [
            'lines' => $cart->lines(),
            'total' => $cart->total(),
        ]);
    }

    private function syncQuantities(Cart $cart): void
    {
        $this->quantities = $cart->lines()
            ->mapWithKeys(fn ($line) => [$line->product->id => $line->quantity])
            ->all();
    }
}
