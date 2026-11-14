<?php

use App\Actions\TransitionOrder;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\Product;

covers(TransitionOrder::class);

dataset('transitions', function () {
    foreach (OrderStatus::cases() as $from) {
        foreach (OrderStatus::cases() as $to) {
            yield "{$from->value} → {$to->value}" => [$from, $to];
        }
    }
});

it('applies allowed transitions and rejects the rest', function (OrderStatus $from, OrderStatus $to) {
    $order = Order::factory()->create(['status' => $from]);

    if ($from->canTransitionTo($to)) {
        expect(app(TransitionOrder::class)->handle($order, $to)->status)->toBe($to)
            ->and($order->isDirty())->toBeFalse()
            ->and($order->fresh()->status)->toBe($to);

        return;
    }

    expect(fn () => app(TransitionOrder::class)->handle($order, $to))
        ->toThrow(InvalidOrderTransition::class);
    expect($order->fresh()->status)->toBe($from);
})->with('transitions');

it('returns the stock when an order is cancelled', function (OrderStatus $from) {
    $mug = Product::factory()->create(['stock' => 5]);
    $grinder = Product::factory()->create(['stock' => 0]);
    $order = Order::factory()->withItem($mug, 2)->withItem($grinder, 1)->create(['status' => $from]);

    app(TransitionOrder::class)->handle($order, OrderStatus::Cancelled);

    expect($mug->fresh()->stock)->toBe(7)
        ->and($grinder->fresh()->stock)->toBe(1);
})->with([OrderStatus::New, OrderStatus::Processing]);

it('skips items whose product was deleted when cancelling', function () {
    $kept = Product::factory()->create(['stock' => 1]);
    $gone = Product::factory()->create();
    $order = Order::factory()->withItem($kept, 1)->withItem($gone, 1)->create();
    $gone->delete();

    app(TransitionOrder::class)->handle($order, OrderStatus::Cancelled);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($kept->fresh()->stock)->toBe(2);
});

it('does not return stock when the cancel is rejected', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $order = Order::factory()->withItem($product, 2)->create(['status' => OrderStatus::Shipped]);

    expect(fn () => app(TransitionOrder::class)->handle($order, OrderStatus::Cancelled))
        ->toThrow(InvalidOrderTransition::class);
    expect($product->fresh()->stock)->toBe(5);
});

it('decides by the stored status, not by a stale copy of the order', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $order = Order::factory()->withItem($product, 2)->create();
    $staleCopy = Order::find($order->id);

    app(TransitionOrder::class)->handle($order, OrderStatus::Cancelled);

    expect(fn () => app(TransitionOrder::class)->handle($staleCopy, OrderStatus::Cancelled))
        ->toThrow(InvalidOrderTransition::class);
    expect($product->fresh()->stock)->toBe(7);
});
