<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Exceptions;

use Src\Ordering\Orders\Domain\ValueObjects\Quantity;
use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class QuantityOutOfRangeException extends BusinessRuleException
{
    /** Build the exception for a line that asks for fewer than 1 or more than 20 portions. */
    public static function create(): self
    {
        return new self('ordering.quantity_out_of_range', ['min' => Quantity::MIN, 'max' => Quantity::MAX]);
    }

    /** The quantity of a line goes from 1 to 20. */
    public function rule(): string
    {
        return 'cantidad-invalida';
    }
}
