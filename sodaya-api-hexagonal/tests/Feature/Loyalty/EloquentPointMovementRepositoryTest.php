<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use DateTimeImmutable;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Loyalty\Points\Domain\Entities\PointMovement;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;
use Src\Loyalty\Points\Domain\ValueObjects\OrderId;
use Src\Loyalty\Points\Domain\ValueObjects\Points;
use Src\Loyalty\Points\Infrastructure\Persistence\Repositories\EloquentPointMovementRepository;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class EloquentPointMovementRepositoryTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private EloquentPointMovementRepository $repository;

    private CustomerId $customer;

    /** A customer of the scenario. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentPointMovementRepository;
        $this->customer = new CustomerId(UserModel::factory()->create()->id);
    }

    /** The balance is the sum of the movements of the customer and nobody else. */
    public function test_balance_adds_up_the_movements_of_the_customer(): void
    {
        $this->assertSame(0, $this->repository->balanceOf($this->customer));

        $this->repository->add($this->purchase($this->order(), 7, '12:00'));
        $this->repository->add($this->redemption(2, '12:30'));
        $this->repository->add(PointMovement::purchase(
            $this->repository->nextId(),
            $other = new CustomerId(UserModel::factory()->create()->id),
            $this->order($other),
            new Points(40),
            new DateTimeImmutable('2026-10-05 12:00'),
        ));

        $this->assertSame(5, $this->repository->balanceOf($this->customer));
    }

    /** A second purchase of the same order is not added, and the repository says so. */
    public function test_an_order_earns_points_once(): void
    {
        $order = $this->order();

        $this->assertTrue($this->repository->add($this->purchase($order, 7, '12:00')));
        $this->assertFalse($this->repository->add($this->purchase($order, 7, '12:01')));
        $this->assertSame(7, $this->repository->balanceOf($this->customer));
    }

    /** The latest movements come newest first, as they were stored. */
    public function test_latest_movements_come_newest_first(): void
    {
        $first = $this->purchase($this->order(), 7, '12:00');
        $second = $this->redemption(2, '12:30');
        $third = $this->purchase($this->order(), 3, '13:00');

        foreach ([$first, $second, $third] as $movement) {
            $this->repository->add($movement);
        }

        $this->assertEquals([$third, $second], $this->repository->latestOf($this->customer, 2));
    }

    /** An order of the customer for a purchase to point to. */
    private function order(?CustomerId $customer = null): OrderId
    {
        return new OrderId(OrderModel::factory()->delivered()->create(['customer_id' => ($customer ?? $this->customer)->value])->id);
    }

    /** Points earned by the customer with an order at a time of 2026-10-05. */
    private function purchase(OrderId $orderId, int $points, string $time): PointMovement
    {
        return PointMovement::purchase($this->repository->nextId(), $this->customer, $orderId, new Points($points), $this->at($time));
    }

    /** Points spent by the customer at a time of 2026-10-05. */
    private function redemption(int $points, string $time): PointMovement
    {
        return PointMovement::redemption($this->repository->nextId(), $this->customer, new Points($points), $this->at($time));
    }

    /** A moment of 2026-10-05 in the time zone of the application. */
    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable("2026-10-05 {$time}", new \DateTimeZone((string) config('app.timezone')));
    }
}
