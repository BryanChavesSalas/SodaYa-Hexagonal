<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Loyalty\Points\Domain\Contracts\PointMovementRepository;
use Src\Loyalty\Points\Domain\Entities\PointMovement;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;
use Src\Loyalty\Points\Domain\ValueObjects\MovementId;
use Src\Loyalty\Points\Domain\ValueObjects\MovementReason;
use Src\Loyalty\Points\Domain\ValueObjects\OrderId;
use Src\Loyalty\Points\Infrastructure\Persistence\Models\PointMovementModel;

final readonly class EloquentPointMovementRepository implements PointMovementRepository
{
    /** Generate a time-ordered identity. */
    public function nextId(): MovementId
    {
        return new MovementId((string) Str::uuid7());
    }

    /** Insert the movement; a second purchase of the same order hits the partial unique index and is ignored. */
    public function add(PointMovement $movement): bool
    {
        return PointMovementModel::query()->insertOrIgnore([
            'id' => $movement->id->value,
            'customer_id' => $movement->customerId->value,
            'order_id' => $movement->orderId?->value,
            'reason' => $movement->reason->value,
            'points' => $movement->points,
            'created_at' => $movement->createdAt->format(DATE_ATOM),
        ]) === 1;
    }

    /**
     * Take an advisory lock keyed by the customer, released at the end of the transaction:
     * there is no balance row to lock, so the lock stands for the whole ledger of the customer.
     */
    public function lockLedgerOf(CustomerId $customerId): void
    {
        DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['point_movements:'.$customerId->value]);
    }

    /** Add up the movements of the customer in the database. */
    public function balanceOf(CustomerId $customerId): int
    {
        return (int) PointMovementModel::query()->where('customer_id', $customerId->value)->sum('points');
    }

    /** Read the latest movements of the customer, newest first. */
    public function latestOf(CustomerId $customerId, int $limit): array
    {
        $models = PointMovementModel::query()
            ->where('customer_id', $customerId->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return array_values($models->map(fn (PointMovementModel $model): PointMovement => PointMovement::reconstitute(
            new MovementId($model->id),
            new CustomerId($model->customer_id),
            $model->order_id === null ? null : new OrderId($model->order_id),
            MovementReason::from($model->reason),
            $model->points,
            $model->created_at,
        ))->all());
    }
}
