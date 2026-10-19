<?php

declare(strict_types=1);

namespace Tests\Unit\Loyalty\Points\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Loyalty\Points\Application\UseCases\GetPointsStatement;
use Src\Loyalty\Points\Domain\Entities\PointMovement;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;
use Src\Loyalty\Points\Domain\ValueObjects\Points;
use Tests\Support\Loyalty\InMemoryPointMovementRepository;

final class GetPointsStatementTest extends TestCase
{
    private const string CUSTOMER = '0192f0c4-0000-7000-8000-00000000c001';

    /** The statement has the whole balance but only the latest 20 movements. */
    public function test_statement_shows_the_balance_and_the_latest_twenty_movements(): void
    {
        $movements = new InMemoryPointMovementRepository;

        for ($i = 1; $i <= 25; $i++) {
            $movements->add(PointMovement::redemption($movements->nextId(), new CustomerId(self::CUSTOMER), new Points($i), new DateTimeImmutable));
        }

        $statement = new GetPointsStatement($movements)->execute(self::CUSTOMER);

        $this->assertSame(-325, $statement->balance);
        $this->assertCount(20, $statement->movements);
        $this->assertSame(-25, $statement->movements[0]->points);
        $this->assertSame(-6, $statement->movements[19]->points);
    }
}
