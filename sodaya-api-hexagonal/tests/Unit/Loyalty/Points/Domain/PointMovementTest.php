<?php

declare(strict_types=1);

namespace Tests\Unit\Loyalty\Points\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Loyalty\Points\Domain\Entities\PointMovement;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;
use Src\Loyalty\Points\Domain\ValueObjects\MovementId;
use Src\Loyalty\Points\Domain\ValueObjects\MovementReason;
use Src\Loyalty\Points\Domain\ValueObjects\OrderId;
use Src\Loyalty\Points\Domain\ValueObjects\Points;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final class PointMovementTest extends TestCase
{
    private const string CUSTOMER = '0192f0c4-0000-7000-8000-00000000c001';

    /** A purchase adds the points it earned and remembers its order. */
    public function test_purchase_adds_points(): void
    {
        $movement = PointMovement::purchase(
            new MovementId('0192f0c4-0000-7000-8000-00000000e001'),
            new CustomerId(self::CUSTOMER),
            new OrderId('0192f0c4-0000-7000-8000-00000000a001'),
            new Points(5),
            new DateTimeImmutable('2026-10-05 12:30'),
        );

        $this->assertSame(MovementReason::Purchase, $movement->reason);
        $this->assertSame(5, $movement->points);
        $this->assertSame('0192f0c4-0000-7000-8000-00000000a001', $movement->orderId?->value);
    }

    /** A redemption takes the points it spends. */
    public function test_redemption_takes_points(): void
    {
        $movement = PointMovement::redemption(
            new MovementId('0192f0c4-0000-7000-8000-00000000e002'),
            new CustomerId(self::CUSTOMER),
            new Points(3),
            new DateTimeImmutable('2026-10-05 12:30'),
        );

        $this->assertSame(MovementReason::Redemption, $movement->reason);
        $this->assertSame(-3, $movement->points);
        $this->assertNull($movement->orderId);
    }

    /** A movement of zero or negative points does not exist: its reason gives the sign. */
    public function test_points_must_be_positive(): void
    {
        $this->expectException(InvalidValueException::class);

        new Points(0);
    }
}
