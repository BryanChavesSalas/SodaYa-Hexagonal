<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class InsufficientPointsException extends BusinessRuleException
{
    /** Build the exception for a redemption larger than the balance. */
    public static function create(int $balance): self
    {
        return new self('loyalty.insufficient_points', ['balance' => $balance]);
    }

    /** The customer asks to spend more points than the balance has. */
    public function rule(): string
    {
        return 'puntos-insuficientes';
    }
}
