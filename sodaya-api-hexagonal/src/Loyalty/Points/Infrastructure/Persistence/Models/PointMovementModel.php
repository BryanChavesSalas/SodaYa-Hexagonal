<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $customer_id
 * @property string|null $order_id
 * @property string $reason
 * @property int $points
 * @property CarbonImmutable $created_at
 */
#[Table('point_movements')]
#[Fillable(['id', 'customer_id', 'order_id', 'reason', 'points', 'created_at'])]
#[WithoutTimestamps]
final class PointMovementModel extends Model
{
    use HasUuids;

    /**
     * Cast the points to an integer and the moment to an immutable date.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
