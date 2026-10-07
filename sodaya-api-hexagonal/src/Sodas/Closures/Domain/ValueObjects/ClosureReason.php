<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ClosureReason
{
    private ?string $value;

    public function __construct(?string $reason)
    {
        $trimmed = $reason !== null ? trim($reason) : null;

        if ($trimmed !== null && mb_strlen($trimmed) > 200) {
            throw new InvalidValueException('closure_reason_too_long');
        }

        $this->value = $trimmed !== '' ? $trimmed : null;
    }

    public function value(): ?string
    {
        return $this->value;
    }
}
