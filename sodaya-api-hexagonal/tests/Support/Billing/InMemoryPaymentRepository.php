<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Shared\Domain\ValueObjects\SodaId;

final class InMemoryPaymentRepository implements PaymentRepository
{
    /** @var array<string, Payment> stored copies, in the order they were opened */
    private array $payments = [];

    private int $sequence = 0;

    public int $saves = 0;

    /** Generate a predictable identity. */
    public function nextId(): PaymentId
    {
        return new PaymentId(sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence));
    }

    /** Store a copy of the attempt unless the order already has a live payment, which is answered instead, as the partial unique index does. */
    public function open(Payment $payment): Payment
    {
        foreach ($this->payments as $stored) {
            if ($stored->orderId->equals($payment->orderId) && $stored->sodaId->equals($payment->sodaId) && $stored->isLive()) {
                return clone $stored;
            }
        }

        $this->payments[$payment->id->value] = clone $payment;

        return $payment;
    }

    /** Replace the stored copy with the outcome. */
    public function save(Payment $payment): void
    {
        $this->saves++;
        $this->payments[$payment->id->value] = clone $payment;
    }

    /** Same as a find: a single process needs no lock. */
    public function findForUpdate(PaymentId $id, SodaId $sodaId): ?Payment
    {
        $payment = $this->payments[$id->value] ?? null;

        return $payment !== null && $payment->sodaId->equals($sodaId) ? clone $payment : null;
    }

    /** The last attempt opened for the order. */
    public function latestOf(OrderId $orderId, SodaId $sodaId): ?Payment
    {
        $latest = null;

        foreach ($this->payments as $payment) {
            if ($payment->orderId->equals($orderId) && $payment->sodaId->equals($sodaId)) {
                $latest = $payment;
            }
        }

        return $latest === null ? null : clone $latest;
    }

    /** Number of attempts stored. */
    public function count(): int
    {
        return count($this->payments);
    }
}
