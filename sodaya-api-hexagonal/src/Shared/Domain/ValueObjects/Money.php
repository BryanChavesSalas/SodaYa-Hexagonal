<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Money
{
    /** Accept an amount of whole colones, never negative; a fraction of a colón does not exist. */
    private function __construct(public int $colones)
    {
        if ($colones < 0) {
            throw new InvalidValueException('shared.money_negative');
        }
    }

    /** Build an amount from whole colones. */
    public static function colones(int $colones): self
    {
        return new self($colones);
    }

    /**
     * Take a percentage off, rounding to the nearest colón with the half colón up, using integers only:
     * 995 with 10 % is 89 550 + 50 = 89 600, divided by 100 is 896.
     */
    public function withDiscount(int $percentage): self
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidValueException('shared.discount_out_of_range', ['min' => 0, 'max' => 100]);
        }

        return new self(intdiv($this->colones * (100 - $percentage) + 50, 100));
    }
}
