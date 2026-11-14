<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Action;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists orders and filters them by status', function () {
    $new = Order::factory()->create();
    $shipped = Order::factory()->create(['status' => OrderStatus::Shipped]);

    livewire(ListOrders::class)
        ->assertCanSeeTableRecords([$new, $shipped])
        ->filterTable('status', OrderStatus::Shipped->value)
        ->assertCanSeeTableRecords([$shipped])
        ->assertCanNotSeeTableRecords([$new]);
});

it('shows the items and the VAT breakdown', function () {
    $product = Product::factory()->create(['name' => 'Moka konvička', 'price' => 12100]);
    $order = Order::factory()->withItem($product, 2)->create();

    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee('Moka konvička')
        ->assertSee(Money::format(24200))
        ->assertSee(Money::format(4200))
        ->assertSee(Money::format(20000));
});

it('offers only the allowed transitions', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionVisible('shipped')
        ->assertActionVisible('cancelled')
        ->assertActionHidden('processing')
        ->assertActionHidden('completed');
});

it('ships an order from the admin', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('shipped')
        ->assertNotified();

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('has no create, edit or delete pages', function () {
    expect(OrderResource::getPages())->toHaveKeys(['index', 'view'])
        ->and(OrderResource::getPages())->toHaveCount(2)
        ->and(OrderResource::canCreate())->toBeFalse();
});

it('titles the page with the order number', function () {
    $order = Order::factory()->create();

    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee("Objednávka {$order->number}")
        ->assertDontSee("Zobrazit {$order->number}");
});

it('does not offer a button called "Zrušit" that keeps the order when cancelling it', function () {
    $order = Order::factory()->create();

    livewire(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionExists('cancelled', function (Action $action): bool {
            return $action->getModalCancelAction()?->getLabel() === 'Zpět'
                && $action->getModalSubmitAction()?->getLabel() === 'Zrušit objednávku';
        });
});
