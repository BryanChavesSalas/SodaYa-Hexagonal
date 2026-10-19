<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ChargeReference
{
    public const int MAX_LENGTH = 255;

    /** Accept the identifier the provider gave the charge, such as pi_... at Stripe. */
    public function __construct(public string $value)
    {
        if (trim($value) === '' || strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException('payments.charge_reference_invalid', ['max' => self::MAX_LENGTH]);
        }
    }

    /** Compare two references by value. */
    public function equals(?self $other): bool
    {
        return $other !== null && $other->value === $this->value;
    }
}
