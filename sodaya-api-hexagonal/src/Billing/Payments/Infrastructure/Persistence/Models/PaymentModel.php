<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $order_id
 * @property string $soda_id
 * @property string $customer_id
 * @property string $provider
 * @property string|null $reference
 * @property int $amount
 * @property string $status
 * @property string|null $decline_code
 * @property string $idempotency_key
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Table('payments')]
#[Fillable([
    'id',
    'order_id',
    'soda_id',
    'customer_id',
    'provider',
    'reference',
    'amount',
    'status',
    'decline_code',
    'idempotency_key',
    'settled_at',
    'created_at',
])]
#[UseFactory(PaymentFactory::class)]
final class PaymentModel extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the amount to an integer and the moments to immutable dates.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'settled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
