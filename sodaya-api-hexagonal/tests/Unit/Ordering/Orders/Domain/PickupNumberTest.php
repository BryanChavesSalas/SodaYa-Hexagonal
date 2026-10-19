<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Domain\ValueObjects\PickupNumber;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final class PickupNumberTest extends TestCase
{
    /** A pickup number goes from 1 to 9999: short enough to call out at the counter, long enough for any day. */
    #[DataProvider('numbers')]
    public function test_pickup_number_keeps_its_range(int $number, bool $valid): void
    {
        if (! $valid) {
            $this->expectException(InvalidValueException::class);
        }

        $this->assertSame($number, new PickupNumber($number)->value);
    }

    /**
     * Numbers at and beyond the limits.
     *
     * @return array<string, array{int, bool}>
     */
    public static function numbers(): array
    {
        return [
            'zero' => [0, false],
            'one' => [1, true],
            'last of the day' => [9_999, true],
            'beyond the last' => [10_000, false],
        ];
    }
}
