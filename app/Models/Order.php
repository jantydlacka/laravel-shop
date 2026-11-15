<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Amounts are integer hellers including VAT, frozen at the time of purchase.
 *
 * @property OrderStatus $status
 */
#[Fillable(['number', 'cart_token', 'status', 'customer_name', 'email', 'phone', 'street', 'city', 'zip', 'note', 'vat_rate', 'total', 'vat_total'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'vat_rate' => 'integer',
            'total' => 'integer',
            'vat_total' => 'integer',
        ];
    }
}
