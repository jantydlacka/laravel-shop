<?php

use App\Enums\OrderStatus;

covers(OrderStatus::class);

$allowed = [
    'new' => ['processing', 'cancelled'],
    'processing' => ['shipped', 'cancelled'],
    'shipped' => ['completed'],
    'completed' => [],
    'cancelled' => [],
];

dataset('pairs', function () {
    foreach (OrderStatus::cases() as $from) {
        foreach (OrderStatus::cases() as $to) {
            yield "{$from->value} → {$to->value}" => [$from, $to];
        }
    }
});

it('allows exactly the defined transitions', function (OrderStatus $from, OrderStatus $to) use ($allowed) {
    expect($from->canTransitionTo($to))->toBe(in_array($to->value, $allowed[$from->value], true));
})->with('pairs');

it('lists allowed transitions in workflow order', function () use ($allowed) {
    foreach (OrderStatus::cases() as $status) {
        expect(array_map(fn (OrderStatus $s) => $s->value, $status->allowedTransitions()))
            ->toBe($allowed[$status->value]);
    }
});

it('has a Czech label for every status', function (OrderStatus $status) {
    expect($status->label())->toBeString()->not->toBeEmpty()->not->toBe($status->value);
})->with(OrderStatus::cases());
