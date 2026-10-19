<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Points
{
    /** Colones of an order that earn one point. */
    public const int COLONES_PER_POINT = 1000;

    /** Accept a positive number of points; a movement says with its reason whether they are added or taken. */
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidValueException('loyalty.points_not_positive');
        }
    }

    /** Points an order of the given amount earns: one for every whole ₡1000; none below ₡1000. */
    public static function earnedWith(int $amount): ?self
    {
        $points = intdiv($amount, self::COLONES_PER_POINT);

        return $points > 0 ? new self($points) : null;
    }
}
