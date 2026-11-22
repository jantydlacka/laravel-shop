<?php

use App\Actions\PlaceOrder;
use App\Cart\Cart;
use App\Exceptions\OrderAlreadyPlacedException;
use App\Livewire\CheckoutPage;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Mail::fake();
});

function apiCustomer(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jana Nováková',
        'email' => 'jana@example.com',
        'phone' => '+420 000 000 000',
        'street' => 'Dlouhá 12',
        'city' => 'Praha',
        'zip' => '11000',
        'note' => 'Zvonit na zvonek',
    ], $overrides);
}

function postOrder(array $items, ?string $token = null, array $customer = [], array $extra = [])
{
    return test()->postJson('/api/orders', array_merge([
        'cart_token' => $token ?? (string) Str::uuid(),
        'items' => $items,
        'customer' => apiCustomer($customer),
    ], $extra));
}

function apiLine(Product $product, int $quantity = 1, ?int $price = null): array
{
    return ['product_id' => $product->id, 'quantity' => $quantity, 'price' => $price ?? $product->price];
}

it('places an order and lowers the stock', function () {
    $product = Product::factory()->create(['stock' => 5, 'price' => 12100]);

    $response = postOrder([apiLine($product, 2)])->assertCreated();

    $order = Order::sole();
    $response->assertExactJson(['data' => [
        'number' => $order->number,
        'status' => $order->status->value,
        'items' => [[
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 12100,
            'quantity' => 2,
            'line_total' => 24200,
        ]],
        'total' => 24200,
        'vat_rate' => (int) config('shop.vat_rate'),
        'vat_total' => $order->vat_total,
    ]]);
    expect($product->fresh()->stock)->toBe(3);
    Mail::assertQueued(OrderPlaced::class, 1);
    Mail::assertQueued(OrderPlaced::class, fn ($mail) => $mail->hasTo('jana@example.com'));
    expect($order->only(['customer_name', 'email', 'phone', 'street', 'city', 'zip', 'note']))->toBe([
        'customer_name' => 'Jana Nováková',
        'email' => 'jana@example.com',
        'phone' => '+420 000 000 000',
        'street' => 'Dlouhá 12',
        'city' => 'Praha',
        'zip' => '11000',
        'note' => 'Zvonit na zvonek',
    ]);
});

it('stores a missing note as null', function () {
    $product = Product::factory()->create(['stock' => 5]);

    postOrder([apiLine($product)], null, ['note' => null])->assertCreated();
    expect(Order::sole()->note)->toBeNull();
    Order::query()->delete();

    $payload = ['cart_token' => (string) Str::uuid(), 'items' => [apiLine($product)], 'customer' => Arr::except(apiCustomer(), 'note')];
    test()->postJson('/api/orders', $payload)->assertCreated();
    expect(Order::sole()->note)->toBeNull();
});

it('returns the same order when the same cart is sent again', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $token = (string) Str::uuid();

    $first = postOrder([apiLine($product, 2)], $token)->assertCreated();
    $second = postOrder([apiLine($product, 2)], $token)->assertOk();

    expect($second->json())->toBe($first->json())
        ->and(Order::count())->toBe(1)
        ->and($product->fresh()->stock)->toBe(3);
    Mail::assertQueued(OrderPlaced::class, 1);
});

it('refuses the same token with different items', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $token = (string) Str::uuid();

    $number = postOrder([apiLine($product, 2)], $token)->assertCreated()->json('data.number');

    postOrder([apiLine($product, 3)], $token)
        ->assertStatus(409)
        ->assertJsonPath('code', 'cart_already_ordered')
        ->assertJsonPath('messages', ["Z tohoto košíku už byla odeslána objednávka č. {$number}. Košík jsme ponechali – zkontrolujte ho a odešlete objednávku znovu, vznikne nová."]);

    expect(Order::count())->toBe(1)->and($product->fresh()->stock)->toBe(3);
});

it('refuses a different cart without an order number when the existing order has none yet', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $token = (string) Str::uuid();
    $order = Order::factory()->withItem($product, 2)->create(['cart_token' => $token]);
    Order::query()->whereKey($order->id)->update(['number' => null]);

    postOrder([apiLine($product, 3)], $token)
        ->assertStatus(409)
        ->assertJsonPath('code', 'cart_already_ordered')
        ->assertJsonPath('messages', ['Z tohoto košíku už byla odeslána objednávka. Košík jsme ponechali – zkontrolujte ho a odešlete objednávku znovu, vznikne nová.']);
});

it('matches a replay regardless of line order and refuses a different set of lines', function () {
    $a = Product::factory()->create(['stock' => 5]);
    $b = Product::factory()->create(['stock' => 5]);
    $token = (string) Str::uuid();

    postOrder([apiLine($a, 1), apiLine($b, 2)], $token)->assertCreated();

    postOrder([apiLine($b, 2), apiLine($a, 1)], $token)->assertOk();
    postOrder([apiLine($a, 1)], $token)->assertStatus(409)->assertJsonPath('code', 'cart_already_ordered');
    postOrder([apiLine($a, 1), apiLine($b, 3)], $token)->assertStatus(409)->assertJsonPath('code', 'cart_already_ordered');

    expect(Order::count())->toBe(1);
});

it('treats a parallel request with the same token like a replay', function () {
    config(['shop.orders_per_hour' => 5]);
    $product = Product::factory()->create(['stock' => 5]);
    $first = postOrder([apiLine($product, 2)])->assertCreated();
    $existing = Order::sole();
    RateLimiter::clear('place-order:127.0.0.1');

    $this->mock(PlaceOrder::class)->shouldReceive('handle')->twice()->andThrow(new OrderAlreadyPlacedException($existing));

    postOrder([apiLine($product, 2)])->assertOk()->assertExactJson($first->json());
    postOrder([apiLine($product, 3)])->assertStatus(409)->assertJsonPath('code', 'cart_already_ordered');

    Mail::assertQueued(OrderPlaced::class, 1);
    expect(RateLimiter::attempts('place-order:127.0.0.1'))->toBe(0)->and(Order::count())->toBe(1);
});

it('reports a changed price and returns the corrected cart', function () {
    $product = Product::factory()->create(['stock' => 5, 'price' => 10000]);

    postOrder([apiLine($product, 1, 9000)])
        ->assertStatus(409)
        ->assertJsonPath('code', 'cart_changed')
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('items', [['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]]);

    expect(Order::count())->toBe(0);
});

it('keeps every line corrected when only one of several changed', function () {
    $fine = Product::factory()->create(['stock' => 5, 'price' => 10000]);
    $changed = Product::factory()->create(['stock' => 5, 'price' => 20000]);

    postOrder([apiLine($fine, 1), apiLine($changed, 2, 15000)])
        ->assertStatus(409)
        ->assertJsonPath('code', 'cart_changed')
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('items', [
            ['product_id' => $fine->id, 'quantity' => 1, 'price' => 10000],
            ['product_id' => $changed->id, 'quantity' => 2, 'price' => 20000],
        ]);

    expect(Order::count())->toBe(0)
        ->and($fine->fresh()->stock)->toBe(5)
        ->and($changed->fresh()->stock)->toBe(5);
});

it('rejects quantity above the limit', function () {
    $product = Product::factory()->create(['stock' => 500]);

    postOrder([apiLine($product, (int) config('shop.max_quantity') + 1)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('items.0.quantity');

    expect(Order::count())->toBe(0);
});

it('lowers quantity above stock with 409', function () {
    $product = Product::factory()->create(['stock' => 2, 'price' => 10000]);

    postOrder([apiLine($product, 4)])
        ->assertStatus(409)
        ->assertJsonPath('code', 'cart_changed')
        ->assertJsonPath('items', [['product_id' => $product->id, 'quantity' => 2, 'price' => 10000]]);

    expect(Order::count())->toBe(0)->and($product->fresh()->stock)->toBe(2);
});

it('returns an empty corrected cart when everything sold out', function () {
    $product = Product::factory()->outOfStock()->create(['price' => 10000]);

    postOrder([apiLine($product)])
        ->assertStatus(409)
        ->assertJsonPath('code', 'cart_changed')
        ->assertJsonPath('items', []);
});

it('reports an unknown product as a changed cart, not a validation error', function () {
    postOrder([['product_id' => 999999, 'quantity' => 1, 'price' => 10000]])
        ->assertStatus(409)
        ->assertJsonPath('code', 'cart_changed')
        ->assertJsonPath('items', []);
});

it('returns the existing order even when the hourly limit is reached', function () {
    config(['shop.orders_per_hour' => 1]);
    $product = Product::factory()->create(['stock' => 5]);
    $token = (string) Str::uuid();

    postOrder([apiLine($product)], $token)->assertCreated();
    postOrder([apiLine($product)], $token)->assertOk();

    expect(Order::count())->toBe(1);
});

it('validates customer fields with Czech messages', function () {
    $product = Product::factory()->create();

    postOrder([apiLine($product)], null, ['zip' => ''])
        ->assertStatus(422)
        ->assertJsonPath('errors', ['customer.zip' => ['Vyplňte PSČ.']]);

    postOrder([apiLine($product)], null, ['zip' => '12'])
        ->assertStatus(422)
        ->assertJsonPath('errors', ['customer.zip' => ['PSČ má pět číslic, např. 110 00.']]);

    postOrder([apiLine($product)], null, ['note' => str_repeat('a', 2001)])
        ->assertStatus(422)
        ->assertJsonPath('errors', ['customer.note' => ['Poznámka může mít nejvýš 2000 znaků.']]);

    expect(Order::count())->toBe(0);
});

it('rejects a missing or malformed cart token', function () {
    $product = Product::factory()->create();

    postOrder([apiLine($product)], 'not-a-uuid')->assertStatus(422)->assertJsonValidationErrors('cart_token');

    test()->postJson('/api/orders', ['items' => [apiLine($product)], 'customer' => apiCustomer()])
        ->assertStatus(422)->assertJsonValidationErrors('cart_token');
});

it('rejects a duplicate product in items', function () {
    $product = Product::factory()->create(['stock' => 5]);

    postOrder([apiLine($product), apiLine($product)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.product_id', 'items.1.product_id']);

    expect(Order::count())->toBe(0);
});

it('casts numeric strings for price and quantity and places the order', function () {
    $product = Product::factory()->create(['stock' => 5, 'price' => 12100]);

    postOrder([['product_id' => (string) $product->id, 'quantity' => '2', 'price' => '12100']])
        ->assertCreated()
        ->assertJsonPath('data.total', 24200);

    expect($product->fresh()->stock)->toBe(3);
});

it('stops after the hourly limit shared with the web checkout', function () {
    config(['shop.orders_per_hour' => 1]);
    $product = Product::factory()->create(['stock' => 10]);

    app(Cart::class)->add($product, 1);
    livewire(CheckoutPage::class)
        ->set('name', 'Jana Nováková')
        ->set('email', 'jana@example.com')
        ->set('phone', '+420 000 000 000')
        ->set('street', 'Dlouhá 12')
        ->set('city', 'Praha')
        ->set('zip', '11000')
        ->call('submit')
        ->assertRedirect();
    expect(Order::count())->toBe(1);

    $retryAfter = postOrder([apiLine($product)])
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_orders')
        ->assertJsonPath('messages', ['Z vaší adresy přišlo v poslední hodině příliš mnoho objednávek. Zkuste to prosím později.'])
        ->headers->get('Retry-After');

    expect($retryAfter)->toMatch('/^\d+$/')
        ->and((int) $retryAfter)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3600);

    expect(Order::count())->toBe(1)->and($product->fresh()->stock)->toBe(9);
});

it('never returns personal data', function () {
    $product = Product::factory()->create(['stock' => 5]);

    $response = postOrder([apiLine($product)])->assertCreated();

    foreach (['customer_name', 'email', 'phone', 'street', 'city', 'zip', 'note'] as $key) {
        $response->assertJsonMissingPath($key)->assertJsonMissingPath("data.$key");
    }
    expect($response->getContent())->not->toContain('jana@example.com')->not->toContain('Dlouhá');

    $replay = postOrder([apiLine($product)], Order::sole()->cart_token)->assertOk();

    foreach (['customer_name', 'email', 'phone', 'street', 'city', 'zip', 'note'] as $key) {
        $replay->assertJsonMissingPath($key)->assertJsonMissingPath("data.$key");
    }
    expect($replay->getContent())->not->toContain('jana@example.com')->not->toContain('Dlouhá');
});

it('does not start a session', function () {
    $product = Product::factory()->create(['stock' => 5]);

    $response = postOrder([apiLine($product)])->assertCreated();

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->has('Set-Cookie'))->toBeFalse();
});

it('rejects an order with more than 100 lines', function () {
    $lines = array_map(fn (int $id) => ['product_id' => $id, 'quantity' => 1, 'price' => 100], range(1, 101));

    postOrder($lines)->assertStatus(422)->assertJsonValidationErrors(['items']);
    expect(Order::count())->toBe(0);
});
