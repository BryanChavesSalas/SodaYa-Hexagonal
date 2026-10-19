<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;

final class MoneyTest extends TestCase
{
    /** The discount is applied in whole colones: 899,1 goes down, 895,5 goes up, and nothing is left with 100 %. */
    #[TestWith([2800, 15, 2380])]
    #[TestWith([2800, 0, 2800])]
    #[TestWith([999, 10, 899])]
    #[TestWith([995, 10, 896])]
    #[TestWith([1500, 100, 0])]
    public function test_applies_the_discount_in_whole_colones(int $price, int $percentage, int $expected): void
    {
        $this->assertSame($expected, Money::colones($price)->withDiscount($percentage)->colones);
    }

    /** A discount outside 0 to 100 is not a discount. */
    #[TestWith([-1])]
    #[TestWith([101])]
    public function test_rejects_a_discount_out_of_range(int $percentage): void
    {
        $this->expectException(InvalidValueException::class);

        Money::colones(2800)->withDiscount($percentage);
    }

    /** An amount is never negative. */
    public function test_rejects_a_negative_amount(): void
    {
        $this->expectException(InvalidValueException::class);

        Money::colones(-1);
    }
}
