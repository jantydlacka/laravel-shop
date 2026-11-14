<?php

namespace App\Cart;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Guest cart in the session: product id => quantity and the unit price the customer saw.
 * The price is compared with the current one when the order is placed.
 */
class Cart
{
    private const SESSION_KEY = 'cart';

    public function __construct(private readonly Session $session) {}

    public function add(Product $product, int $quantity): void
    {
        $items = $this->items();

        $items[$product->id] = [
            'quantity' => ($items[$product->id]['quantity'] ?? 0) + $quantity,
            'price' => $product->price,
        ];

        $this->store($items);
    }

    public function update(int $productId, int $quantity): void
    {
        $items = $this->items();

        if (isset($items[$productId])) {
            $items[$productId]['quantity'] = $quantity;
            $this->store($items);
        }
    }

    public function remove(int $productId): void
    {
        $items = $this->items();
        unset($items[$productId]);

        $this->store($items);
    }

    public function quantityOf(int $productId): int
    {
        return $this->items()[$productId]['quantity'] ?? 0;
    }

    /**
     * Lines whose product still exists, in the order they were added.
     *
     * @return Collection<int, CartLine>
     */
    public function lines(): Collection
    {
        $items = $this->items();
        $products = Product::query()->whereKey(array_keys($items))->get()->keyBy('id');

        return collect($items)
            ->filter(fn (array $item, int $id) => $products->has($id))
            ->map(fn (array $item, int $id) => new CartLine($products[$id], $item['quantity'], $item['price']))
            ->values();
    }

    public function total(): int
    {
        return array_sum(array_map(fn (array $item) => $item['quantity'] * $item['price'], $this->items()));
    }

    public function count(): int
    {
        return array_sum(array_column($this->items(), 'quantity'));
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * Aligns the cart with current prices, availability and stock.
     *
     * @return list<string> what changed, in Czech, for the customer
     */
    public function refresh(): array
    {
        $items = $this->items();
        $products = Product::query()->whereKey(array_keys($items))->get()->keyBy('id');
        $messages = [];

        foreach ($items as $id => $item) {
            $product = $products->get($id);

            if ($product === null) {
                unset($items[$id]);
                $messages[] = 'Jeden z produktů už není v nabídce, odebrali jsme ho z košíku.';

                continue;
            }

            if (! $product->is_available) {
                unset($items[$id]);
                $messages[] = "{$product->name} už není v nabídce, odebrali jsme ho z košíku.";

                continue;
            }

            if (! $product->isInStock()) {
                unset($items[$id]);
                $messages[] = "{$product->name} je vyprodaný, odebrali jsme ho z košíku.";

                continue;
            }

            if ($item['quantity'] > $product->stock) {
                $items[$id]['quantity'] = $product->stock;
                $messages[] = "{$product->name}: skladem je jen {$product->stock} ks, množství jsme snížili.";
            }

            if ($item['price'] !== $product->price) {
                $items[$id]['price'] = $product->price;
                $messages[] = "{$product->name}: cena se změnila z ".Money::format($item['price']).' na '.Money::format($product->price).'.';
            }
        }

        $this->store($items);

        return $messages;
    }

    /**
     * Largest quantity of the product the cart may hold in total.
     */
    public static function maxQuantityFor(Product $product): int
    {
        return min($product->stock, (int) config('shop.max_quantity'));
    }

    /**
     * Raw cart content: product id => quantity and the unit price the customer saw.
     *
     * @return array<int, array{quantity: int, price: int}>
     */
    public function items(): array
    {
        return $this->session->get(self::SESSION_KEY, []);
    }

    /**
     * @param  array<int, array{quantity: int, price: int}>  $items
     */
    private function store(array $items): void
    {
        $this->session->put(self::SESSION_KEY, $items);
    }
}
