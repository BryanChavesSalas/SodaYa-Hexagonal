<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Entities;

use DateTimeImmutable;
use Src\Billing\Payments\Domain\ValueObjects\Amount;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\CustomerId;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;

final class Payment
{
    /** Prefix of the key sent to the provider; the rest is the identity of the attempt. */
    public const string IDEMPOTENCY_KEY_PREFIX = 'pago-';

    private const string PROVIDER_PATTERN = '/^[a-z][a-z0-9_]{0,19}$/';

    /** Hold the full state of an attempt to pay an order. */
    private function __construct(
        public readonly PaymentId $id,
        public readonly OrderId $orderId,
        public readonly SodaId $sodaId,
        public readonly CustomerId $customerId,
        public readonly string $provider,
        public readonly Amount $amount,
        public readonly string $idempotencyKey,
        public private(set) PaymentStatus $status,
        public private(set) ?ChargeReference $reference,
        public private(set) ?string $declineCode,
        public readonly DateTimeImmutable $openedAt,
        public private(set) ?DateTimeImmutable $settledAt,
    ) {}

    /**
     * Open an attempt to pay the order with a provider: pending, without a reference yet, with the amount the order
     * is worth and an idempotency key derived from its own identity, so a retry towards the provider repeats the same charge.
     */
    public static function open(
        PaymentId $id,
        OrderId $orderId,
        SodaId $sodaId,
        CustomerId $customerId,
        string $provider,
        Amount $amount,
        DateTimeImmutable $openedAt,
    ): self {
        if (preg_match(self::PROVIDER_PATTERN, $provider) !== 1) {
            throw new InvalidValueException('payments.provider_invalid');
        }

        return new self(
            $id,
            $orderId,
            $sodaId,
            $customerId,
            $provider,
            $amount,
            self::IDEMPOTENCY_KEY_PREFIX.$id->value,
            PaymentStatus::Pending,
            null,
            null,
            $openedAt,
            null,
        );
    }

    /** Rebuild a payment from its stored state. */
    public static function reconstitute(
        PaymentId $id,
        OrderId $orderId,
        SodaId $sodaId,
        CustomerId $customerId,
        string $provider,
        Amount $amount,
        string $idempotencyKey,
        PaymentStatus $status,
        ?ChargeReference $reference,
        ?string $declineCode,
        DateTimeImmutable $openedAt,
        ?DateTimeImmutable $settledAt,
    ): self {
        return new self($id, $orderId, $sodaId, $customerId, $provider, $amount, $idempotencyKey, $status, $reference, $declineCode, $openedAt, $settledAt);
    }

    /** What the provider needs to charge this attempt, with the payment method the customer chose. */
    public function asChargeRequest(string $paymentMethod): ChargeRequest
    {
        return new ChargeRequest($this->idempotencyKey, $this->amount, $paymentMethod, $this->orderId);
    }

    /**
     * Record what the provider answered. Approved or rejected closes the payment at that moment; a provider still
     * deciding only leaves its reference, so the charge can be asked about later. A closed payment never changes.
     * Answer whether anything changed.
     */
    public function settle(ChargeResult $result, DateTimeImmutable $at): bool
    {
        if ($this->isClosed()) {
            return false;
        }

        $changed = false;

        if ($result->reference !== null && ! $result->reference->equals($this->reference)) {
            $this->reference = $result->reference;
            $changed = true;
        }

        if ($result->pending()) {
            return $changed;
        }

        $this->status = $result->status;
        $this->declineCode = $result->declineCode;
        $this->settledAt = $at;

        return true;
    }

    /** Whether the provider has not decided yet. */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    /** Whether the provider charged the amount. */
    public function isApproved(): bool
    {
        return $this->status === PaymentStatus::Approved;
    }

    /** Whether the bank declined the charge. */
    public function isRejected(): bool
    {
        return $this->status === PaymentStatus::Rejected;
    }

    /** Whether the payment counts as the live payment of its order: pending or approved. */
    public function isLive(): bool
    {
        return $this->status->isLive();
    }

    /** Whether the provider already decided: approved or rejected. */
    private function isClosed(): bool
    {
        return ! $this->isPending();
    }
}
