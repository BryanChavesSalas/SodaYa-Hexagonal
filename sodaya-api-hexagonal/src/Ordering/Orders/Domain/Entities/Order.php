<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Entities;

use DateTimeImmutable;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotPaidException;
use Src\Ordering\Orders\Domain\Exceptions\TransitionNotAllowedException;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Src\Ordering\Orders\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;

final class Order
{
    public const int MIN_LINES = 1;

    public const int MAX_LINES = 20;

    /** Minutes the customer has to pay a new order before it is cancelled. */
    public const int MINUTES_TO_PAY = 10;

    /** Minutes the kitchen has to confirm a paid order before it is cancelled and refunded. */
    public const int MINUTES_TO_CONFIRM = 10;

    /**
     * Hold the full state of an order.
     *
     * @param  list<OrderLine>  $lines
     * @param  array<string, DateTimeImmutable>  $statusChanges  moment each later status was reached, by status value
     */
    private function __construct(
        public readonly OrderId $id,
        public readonly SodaId $sodaId,
        public readonly CustomerId $customerId,
        public readonly array $lines,
        public private(set) OrderStatus $status,
        public readonly DateTimeImmutable $placedAt,
        public private(set) array $statusChanges,
        public private(set) ?CancellationOrigin $cancellationOrigin,
        public private(set) PaymentStatus $paymentStatus,
        public private(set) ?DateTimeImmutable $paidAt,
    ) {}

    /**
     * Place a new order: it starts pending, with between 1 and 20 lines and one line per dish.
     *
     * @param  list<OrderLine>  $lines
     */
    public static function place(
        OrderId $id,
        SodaId $sodaId,
        CustomerId $customerId,
        array $lines,
        DateTimeImmutable $placedAt,
    ): self {
        if (count($lines) < self::MIN_LINES || count($lines) > self::MAX_LINES) {
            throw new InvalidValueException('ordering.lines_out_of_range', ['min' => self::MIN_LINES, 'max' => self::MAX_LINES]);
        }

        $dishes = array_map(fn (OrderLine $line): string => $line->dishId->value, $lines);

        if (count(array_unique($dishes)) !== count($dishes)) {
            throw new InvalidValueException('ordering.dish_repeated');
        }

        return new self($id, $sodaId, $customerId, $lines, OrderStatus::Pending, $placedAt, [], null, PaymentStatus::Pending, null);
    }

    /**
     * Rebuild an order from its stored state.
     *
     * @param  list<OrderLine>  $lines
     * @param  array<string, DateTimeImmutable>  $statusChanges
     */
    public static function reconstitute(
        OrderId $id,
        SodaId $sodaId,
        CustomerId $customerId,
        array $lines,
        OrderStatus $status,
        DateTimeImmutable $placedAt,
        array $statusChanges,
        ?CancellationOrigin $cancellationOrigin,
        PaymentStatus $paymentStatus = PaymentStatus::Pending,
        ?DateTimeImmutable $paidAt = null,
    ): self {
        return new self($id, $sodaId, $customerId, $lines, $status, $placedAt, $statusChanges, $cancellationOrigin, $paymentStatus, $paidAt);
    }

    /** Record that the payment of the order was approved; approving it again keeps the first moment and answers false. */
    public function approvePayment(DateTimeImmutable $at): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        $this->paymentStatus = PaymentStatus::Approved;
        $this->paidAt = $at;

        return true;
    }

    /** Whether the payment of the order was approved. */
    public function isPaid(): bool
    {
        return $this->paymentStatus === PaymentStatus::Approved;
    }

    /** Move the order one step forward in the kitchen flow: pending, confirmed, ready, delivered; confirming needs an approved payment. */
    public function advanceTo(OrderStatus $target, DateTimeImmutable $at): void
    {
        if ($target === OrderStatus::Cancelled || ! $this->status->canChangeTo($target)) {
            throw TransitionNotAllowedException::create();
        }

        if ($target === OrderStatus::Confirmed && ! $this->isPaid()) {
            throw OrderNotPaidException::create();
        }

        $this->changeTo($target, $at);
    }

    /** Cancel a pending or confirmed order; cancelling it again has no effect and answers false. */
    public function cancel(CancellationOrigin $origin, DateTimeImmutable $at): bool
    {
        if ($this->status === OrderStatus::Cancelled) {
            return false;
        }

        if (! $this->status->canChangeTo(OrderStatus::Cancelled)) {
            throw TransitionNotAllowedException::create();
        }

        $this->cancellationOrigin = $origin;
        $this->changeTo(OrderStatus::Cancelled, $at);

        return true;
    }

    /** Moment after which a pending order without an approved payment is cancelled. */
    public function paymentDeadline(): DateTimeImmutable
    {
        return $this->placedAt->modify('+'.self::MINUTES_TO_PAY.' minutes');
    }

    /** Moment after which a paid order the kitchen has not confirmed is cancelled; null while it is unpaid. */
    public function confirmationDeadline(): ?DateTimeImmutable
    {
        return $this->paidAt?->modify('+'.self::MINUTES_TO_CONFIRM.' minutes');
    }

    /**
     * Cancel the order if its time ran out: a pending order still unpaid at its payment deadline,
     * or paid and still unconfirmed at its confirmation deadline. Answer whether it cancelled.
     */
    public function expire(DateTimeImmutable $now): bool
    {
        if ($this->status !== OrderStatus::Pending) {
            return false;
        }

        if (! $this->isPaid() && $now >= $this->paymentDeadline()) {
            return $this->cancel(CancellationOrigin::PaymentTimeout, $now);
        }

        $confirmationDeadline = $this->confirmationDeadline();

        if ($confirmationDeadline !== null && $now >= $confirmationDeadline) {
            return $this->cancel(CancellationOrigin::ConfirmationTimeout, $now);
        }

        return false;
    }

    /** Moment the order reached a status, if it did; pending is the moment it was placed. */
    public function changedAt(OrderStatus $status): ?DateTimeImmutable
    {
        return $status === OrderStatus::Pending ? $this->placedAt : $this->statusChanges[$status->value] ?? null;
    }

    /** Apply a valid change and record when it happened. */
    private function changeTo(OrderStatus $status, DateTimeImmutable $at): void
    {
        $this->status = $status;
        $this->statusChanges[$status->value] = $at;
    }

    /** Amount of the order in colones: the sum of its lines. */
    public function total(): int
    {
        return array_sum(array_map(fn (OrderLine $line): int => $line->subtotal(), $this->lines));
    }
}
