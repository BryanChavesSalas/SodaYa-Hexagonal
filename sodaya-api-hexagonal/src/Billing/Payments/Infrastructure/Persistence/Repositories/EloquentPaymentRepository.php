<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Persistence\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\ValueObjects\Amount;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\CustomerId;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Billing\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class EloquentPaymentRepository implements PaymentRepository
{
    /** Statuses of a payment that counts as the live one of its order, as the partial unique index lists them. */
    private const array LIVE_STATUSES = ['pending', 'approved'];

    /** Generate a time-ordered UUID for a new attempt. */
    public function nextId(): PaymentId
    {
        return new PaymentId((string) Str::uuid7());
    }

    /**
     * Insert the attempt inside a savepoint. When the order already has a live payment, the partial unique index
     * refuses the row, the savepoint is rolled back and the payment that stands is answered instead; a race between
     * two requests ends the same way, because only one insert can win the index.
     */
    public function open(Payment $payment): Payment
    {
        try {
            DB::transaction(fn () => PaymentModel::query()->create([
                'id' => $payment->id->value,
                'order_id' => $payment->orderId->value,
                'soda_id' => $payment->sodaId->value,
                'customer_id' => $payment->customerId->value,
                'provider' => $payment->provider,
                'amount' => $payment->amount->colones,
                'idempotency_key' => $payment->idempotencyKey,
                'created_at' => $payment->openedAt,
                ...$this->outcomeColumns($payment),
            ]));
        } catch (UniqueConstraintViolationException $exception) {
            return $this->liveOf($payment->orderId, $payment->sodaId) ?? throw $exception;
        }

        return $payment;
    }

    /** Update the columns that change when the provider answers. */
    public function save(Payment $payment): void
    {
        PaymentModel::query()
            ->where('soda_id', $payment->sodaId->value)
            ->whereKey($payment->id->value)
            ->update($this->outcomeColumns($payment));
    }

    /** Find the payment scoped to its soda with a row lock: SELECT ... FOR UPDATE. */
    public function findForUpdate(PaymentId $id, SodaId $sodaId): ?Payment
    {
        $model = PaymentModel::query()
            ->where('soda_id', $sodaId->value)
            ->lockForUpdate()
            ->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /** The newest attempt of the order, by the moment it was opened. */
    public function latestOf(OrderId $orderId, SodaId $sodaId): ?Payment
    {
        $model = PaymentModel::query()
            ->where('soda_id', $sodaId->value)
            ->where('order_id', $orderId->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** The pending or approved payment of the order, if there is one. */
    private function liveOf(OrderId $orderId, SodaId $sodaId): ?Payment
    {
        $model = PaymentModel::query()
            ->where('soda_id', $sodaId->value)
            ->where('order_id', $orderId->value)
            ->whereIn('status', self::LIVE_STATUSES)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * Columns that hold the outcome of the payment.
     *
     * @return array<string, mixed>
     */
    private function outcomeColumns(Payment $payment): array
    {
        return [
            'status' => $payment->status->value,
            'reference' => $payment->reference?->value,
            'decline_code' => $payment->declineCode,
            'settled_at' => $payment->settledAt,
        ];
    }

    /** Rebuild the payment from its stored state. */
    private function toDomain(PaymentModel $model): Payment
    {
        return Payment::reconstitute(
            new PaymentId($model->id),
            new OrderId($model->order_id),
            new SodaId($model->soda_id),
            new CustomerId($model->customer_id),
            $model->provider,
            new Amount($model->amount),
            $model->idempotency_key,
            PaymentStatus::from($model->status),
            $model->reference === null ? null : new ChargeReference($model->reference),
            $model->decline_code,
            $model->created_at->toDateTimeImmutable(),
            $model->settled_at?->toDateTimeImmutable(),
        );
    }
}
