<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class DishNotOfSodaException extends BusinessRuleException
{
    /** Build the exception for a line that names a dish the soda does not have. */
    public static function create(): self
    {
        return new self('ordering.dish_not_of_soda');
    }

    /** Every dish of an order belongs to the soda of the order. */
    public function rule(): string
    {
        return 'plato-no-pertenece-a-la-soda';
    }
}
