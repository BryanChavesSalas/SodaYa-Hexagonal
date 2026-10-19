<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Contracts;

use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Shared\Domain\ValueObjects\SodaId;

interface PaymentRepository
{
    /** Generate the identity of a new attempt. */
    public function nextId(): PaymentId;

    /**
     * Keep at most one live payment per order: store the attempt unless the order already has a pending or approved
     * payment, and answer the one that stands, the new attempt or the live one.
     */
    public function open(Payment $payment): Payment;

    /** Persist the outcome of a payment: its status, its reference, the code of the bank and the moment it closed. */
    public function save(Payment $payment): void;

    /** Find a payment of the soda and lock it until the transaction ends, so two settlements never overlap. */
    public function findForUpdate(PaymentId $id, SodaId $sodaId): ?Payment;

    /** The latest attempt to pay the order, live or not. */
    public function latestOf(OrderId $orderId, SodaId $sodaId): ?Payment;
}
