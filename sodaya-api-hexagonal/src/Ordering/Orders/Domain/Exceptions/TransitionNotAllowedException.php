<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class TransitionNotAllowedException extends BusinessRuleException
{
    /** Build the exception for a jump, a step back or a change after the order ended. */
    public static function create(): self
    {
        return new self('ordering.transition_not_allowed');
    }

    /** The order cannot move to the requested status. */
    public function rule(): string
    {
        return 'transicion-no-permitida';
    }
}
