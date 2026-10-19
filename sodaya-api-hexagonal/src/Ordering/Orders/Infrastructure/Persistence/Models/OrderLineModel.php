<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Persistence\Models;

use Database\Factories\OrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $order_id
 * @property string $soda_id
 * @property string $dish_id
 * @property string $dish_name
 * @property int $unit_price
 * @property int $quantity
 * @property int $preparation_minutes
 */
#[Table('order_lines')]
#[Fillable(['id', 'order_id', 'soda_id', 'dish_id', 'dish_name', 'unit_price', 'quantity', 'preparation_minutes'])]
#[UseFactory(OrderLineFactory::class)]
#[WithoutTimestamps]
final class OrderLineModel extends Model
{
    /** @use HasFactory<OrderLineFactory> */
    use HasFactory, HasUuids;

    /**
     * Order the line belongs to.
     *
     * @return BelongsTo<OrderModel, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    /**
     * Cast the numeric columns to integers.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'preparation_minutes' => 'integer',
        ];
    }
}
