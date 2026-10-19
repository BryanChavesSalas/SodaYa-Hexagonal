<?php

declare(strict_types=1);

namespace Tests\Unit\Loyalty\Points\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Loyalty\Points\Application\DTOs\RedeemPointsCommand;
use Src\Loyalty\Points\Application\UseCases\RedeemPoints;
use Src\Loyalty\Points\Domain\Entities\PointMovement;
use Src\Loyalty\Points\Domain\Exceptions\InsufficientPointsException;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;
use Src\Loyalty\Points\Domain\ValueObjects\OrderId;
use Src\Loyalty\Points\Domain\ValueObjects\Points;
use Tests\Support\Loyalty\InMemoryPointMovementRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

final class RedeemPointsTest extends TestCase
{
    private const string CUSTOMER = '0192f0c4-0000-7000-8000-00000000c001';

    private InMemoryPointMovementRepository $movements;

    private RecordingTransactionRunner $transaction;

    private RedeemPoints $redeemPoints;

    /** A customer with 10 points from one order. */
    protected function setUp(): void
    {
        $this->movements = new InMemoryPointMovementRepository;
        $this->transaction = new RecordingTransactionRunner;
        $this->redeemPoints = new RedeemPoints($this->movements, $this->transaction);

        $this->movements->add(PointMovement::purchase(
            $this->movements->nextId(),
            new CustomerId(self::CUSTOMER),
            new OrderId('0192f0c4-0000-7000-8000-00000000a001'),
            new Points(10),
            new DateTimeImmutable('2026-10-05 12:00'),
        ));
    }

    /** A redemption within the balance is written and answers what is left, with the ledger locked first. */
    public function test_redemption_within_the_balance_is_written(): void
    {
        $redemption = $this->redeemPoints->execute(new RedeemPointsCommand(self::CUSTOMER, 10), new DateTimeImmutable);

        $this->assertSame(-10, $redemption->movement->points);
        $this->assertSame(0, $redemption->balance);
        $this->assertSame(0, $this->movements->balanceOf(new CustomerId(self::CUSTOMER)));
        $this->assertSame([self::CUSTOMER], $this->movements->locked);
    }

    /** A redemption above the balance is refused and writes nothing. */
    public function test_redemption_above_the_balance_is_refused(): void
    {
        try {
            $this->redeemPoints->execute(new RedeemPointsCommand(self::CUSTOMER, 11), new DateTimeImmutable);
            $this->fail('A redemption above the balance was accepted.');
        } catch (InsufficientPointsException $exception) {
            $this->assertSame('puntos-insuficientes', $exception->rule());
            $this->assertSame(['balance' => 10], $exception->parameters());
            $this->assertSame(10, $this->movements->balanceOf(new CustomerId(self::CUSTOMER)));
            $this->assertSame(1, $this->transaction->rolledBack());
        }
    }
}
