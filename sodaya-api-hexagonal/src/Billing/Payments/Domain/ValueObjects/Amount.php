<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Amount
{
    /** Accept a whole number of colones greater than zero; each provider converts it to its own unit. */
    public function __construct(public int $colones)
    {
        if ($colones < 1) {
            throw new InvalidValueException('payments.amount_not_positive');
        }
    }
}
