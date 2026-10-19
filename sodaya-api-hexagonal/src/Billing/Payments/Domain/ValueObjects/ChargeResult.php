<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ChargeResult
{
    /**
     * What the provider answered about a charge: the status the payment takes, the reference the provider
     * gave the charge, when it gave one, and the code of the bank when it declined.
     */
    public function __construct(
        public PaymentStatus $status,
        public ?ChargeReference $reference = null,
        public ?string $declineCode = null,
    ) {
        if ($status === PaymentStatus::Approved && $reference === null) {
            throw new InvalidValueException('payments.charge_result_invalid');
        }

        if ($status === PaymentStatus::Rejected && ($declineCode === null || trim($declineCode) === '')) {
            throw new InvalidValueException('payments.charge_result_invalid');
        }
    }

    /** The provider charged the amount. */
    public function approved(): bool
    {
        return $this->status === PaymentStatus::Approved;
    }

    /** The bank declined the charge: a business outcome, not an error. */
    public function rejected(): bool
    {
        return $this->status === PaymentStatus::Rejected;
    }

    /** The provider has not decided yet; the charge is asked about later. */
    public function pending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
}
