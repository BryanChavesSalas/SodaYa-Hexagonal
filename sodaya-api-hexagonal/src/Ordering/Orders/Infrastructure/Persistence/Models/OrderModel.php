<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $soda_id
 * @property string $customer_id
 * @property string $status
 * @property string|null $cancellation_origin
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string $payment_status
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable $created_at
 * @property-read Collection<int, OrderLineModel> $lines
 */
#[Table('orders')]
#[Fillable([
    'id',
    'soda_id',
    'customer_id',
    'status',
    'cancellation_origin',
    'confirmed_at',
    'ready_at',
    'delivered_at',
    'cancelled_at',
    'payment_status',
    'paid_at',
    'created_at',
])]
#[UseFactory(OrderFactory::class)]
final class OrderModel extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUuids;

    /**
     * Lines of the order.
     *
     * @return HasMany<OrderLineModel, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLineModel::class, 'order_id');
    }

    /**
     * Cast the moments of each status to immutable dates.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }
}
