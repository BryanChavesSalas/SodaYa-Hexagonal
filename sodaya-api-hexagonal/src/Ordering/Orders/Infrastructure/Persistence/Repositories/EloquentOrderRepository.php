<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Entities\OrderLine;
use Src\Ordering\Orders\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\DishId;
use Src\Ordering\Orders\Domain\ValueObjects\IdempotencyKey;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Src\Ordering\Orders\Domain\ValueObjects\PaymentStatus;
use Src\Ordering\Orders\Domain\ValueObjects\PickupNumber;
use Src\Ordering\Orders\Domain\ValueObjects\Quantity;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class EloquentOrderRepository implements OrderRepository
{
    /**
     * Column that keeps the moment of each status after the first one.
     *
     * @var array<string, string>
     */
    private const array MOMENTS = [
        'confirmed' => 'confirmed_at',
        'ready' => 'ready_at',
        'delivered' => 'delivered_at',
        'cancelled' => 'cancelled_at',
    ];

    /** Generate a time-ordered UUID for a new order. */
    public function nextId(): OrderId
    {
        return new OrderId((string) Str::uuid7());
    }

    /**
     * Insert the order and its lines once; afterwards only its status, its payment and their moments change.
     * The unique key per customer turns a second order with the same key into a domain exception.
     */
    public function save(Order $order): void
    {
        try {
            DB::transaction(function () use ($order): void {
                $model = OrderModel::query()->find($order->id->value);

                if ($model === null) {
                    $this->insert($order);

                    return;
                }

                $model->update($this->statusColumns($order));
            });
        } catch (UniqueConstraintViolationException) {
            throw IdempotencyKeyReusedException::create();
        }
    }

    /** Find the order the customer placed with the key; the lines come with it. */
    public function findByIdempotencyKey(CustomerId $customerId, string $key): ?Order
    {
        $model = OrderModel::query()
            ->with('lines')
            ->where('customer_id', $customerId->value)
            ->where('idempotency_key', $key)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** Find the order scoped to its soda. */
    public function find(OrderId $id, SodaId $sodaId): ?Order
    {
        $model = OrderModel::query()
            ->with('lines')
            ->where('soda_id', $sodaId->value)
            ->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /** Find the order scoped to its soda with a row lock: SELECT ... FOR UPDATE. */
    public function findForUpdate(OrderId $id, SodaId $sodaId): ?Order
    {
        $model = OrderModel::query()
            ->with('lines')
            ->where('soda_id', $sodaId->value)
            ->lockForUpdate()
            ->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /** Insert a new order and its lines. */
    private function insert(Order $order): void
    {
        OrderModel::query()->create([
            'id' => $order->id->value,
            'soda_id' => $order->sodaId->value,
            'customer_id' => $order->customerId->value,
            'idempotency_key' => $order->idempotencyKey?->value,
            'request_fingerprint' => $order->idempotencyKey?->fingerprint,
            'pickup_date' => $order->placedAt->setTimezone(new DateTimeZone('America/Costa_Rica'))->format('Y-m-d'),
            'pickup_number' => $order->pickupNumber->value,
            'created_at' => $order->placedAt,
            ...$this->statusColumns($order),
        ]);

        foreach ($order->lines as $line) {
            OrderLineModel::query()->create([
                'order_id' => $order->id->value,
                'soda_id' => $order->sodaId->value,
                'dish_id' => $line->dishId->value,
                'dish_name' => $line->dishName,
                'unit_price' => $line->unitPrice,
                'quantity' => $line->quantity->value,
                'preparation_minutes' => $line->preparationMinutes,
            ]);
        }
    }

    /**
     * Columns that change with the status of the order.
     *
     * @return array<string, mixed>
     */
    private function statusColumns(Order $order): array
    {
        $columns = [
            'status' => $order->status->value,
            'cancellation_origin' => $order->cancellationOrigin?->value,
            'payment_status' => $order->paymentStatus->value,
            'paid_at' => $order->paidAt,
        ];

        foreach (self::MOMENTS as $status => $column) {
            $columns[$column] = $order->changedAt(OrderStatus::from($status));
        }

        return $columns;
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(OrderModel $model): Order
    {
        $changes = [];

        foreach (self::MOMENTS as $status => $column) {
            $moment = $model->{$column};

            if ($moment instanceof DateTimeImmutable) {
                $changes[$status] = $moment;
            }
        }

        return Order::reconstitute(
            new OrderId($model->id),
            new SodaId($model->soda_id),
            new CustomerId($model->customer_id),
            array_values($model->lines->sortBy('id')->map(fn (OrderLineModel $line): OrderLine => new OrderLine(
                new DishId($line->dish_id),
                $line->dish_name,
                $line->unit_price,
                new Quantity($line->quantity),
                $line->preparation_minutes,
            ))->all()),
            OrderStatus::from($model->status),
            $model->created_at->toDateTimeImmutable(),
            new PickupNumber($model->pickup_number),
            $changes,
            $model->cancellation_origin === null ? null : CancellationOrigin::from($model->cancellation_origin),
            PaymentStatus::from($model->payment_status),
            $model->paid_at?->toDateTimeImmutable(),
            $model->idempotency_key === null || $model->request_fingerprint === null
                ? null
                : new IdempotencyKey($model->idempotency_key, $model->request_fingerprint),
        );
    }
}
