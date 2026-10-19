<?php

declare(strict_types=1);

namespace Tests\Unit\Loyalty\Points\Application;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Loyalty\Points\Application\DTOs\AwardPurchasePointsCommand;
use Src\Loyalty\Points\Application\UseCases\AwardPurchasePoints;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;
use Tests\Support\Loyalty\InMemoryPointMovementRepository;

final class AwardPurchasePointsTest extends TestCase
{
    private const string CUSTOMER = '0192f0c4-0000-7000-8000-00000000c001';

    private const string ORDER = '0192f0c4-0000-7000-8000-00000000a001';

    private InMemoryPointMovementRepository $movements;

    private AwardPurchasePoints $awardPoints;

    /** Wire the use case to an in-memory ledger. */
    protected function setUp(): void
    {
        $this->movements = new InMemoryPointMovementRepository;
        $this->awardPoints = new AwardPurchasePoints($this->movements);
    }

    /** One point for every whole ₡1000 of the order. */
    #[DataProvider('amounts')]
    public function test_one_point_per_whole_thousand_colones(int $amount, int $points): void
    {
        $this->awardPoints->execute(new AwardPurchasePointsCommand(self::CUSTOMER, self::ORDER, $amount), new DateTimeImmutable);

        $this->assertSame($points, $this->movements->balanceOf(new CustomerId(self::CUSTOMER)));
    }

    /**
     * Amounts of an order and the points they earn.
     *
     * @return array<string, array{int, int}>
     */
    public static function amounts(): array
    {
        return [
            'below a thousand' => [999, 0],
            'exactly a thousand' => [1_000, 1],
            'two casados' => [5_600, 5],
            'casado and fresco' => [6_600, 6],
        ];
    }

    /** Running it again for the same order adds nothing. */
    public function test_an_order_earns_points_once(): void
    {
        $command = new AwardPurchasePointsCommand(self::CUSTOMER, self::ORDER, 5_600);

        $this->assertTrue($this->awardPoints->execute($command, new DateTimeImmutable));
        $this->assertFalse($this->awardPoints->execute($command, new DateTimeImmutable));
        $this->assertSame(5, $this->movements->balanceOf(new CustomerId(self::CUSTOMER)));
    }
}
