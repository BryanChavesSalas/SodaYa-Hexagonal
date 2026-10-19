<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class DishUnavailableException extends BusinessRuleException
{
    /** Build the exception for a line that names a dish taken off the menu. */
    public static function create(): self
    {
        return new self('ordering.dish_unavailable');
    }

    /** Only an active dish can be ordered. */
    public function rule(): string
    {
        return 'plato-no-disponible';
    }
}
