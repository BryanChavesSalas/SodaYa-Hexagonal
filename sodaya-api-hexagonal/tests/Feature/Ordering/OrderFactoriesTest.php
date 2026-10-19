<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class OrderFactoriesTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** Every state of the factory produces a row the database accepts. */
    #[DataProvider('states')]
    public function test_each_state_creates_a_valid_order(string $state, string $status): void
    {
        $order = $state === 'pending' ? OrderModel::factory()->create() : OrderModel::factory()->{$state}()->create();

        $this->assertSame($status, $order->refresh()->status);
    }

    /**
     * Factory states and the status they leave.
     *
     * @return array<string, array{string, string}>
     */
    public static function states(): array
    {
        return [
            'pending' => ['pending', 'pending'],
            'confirmed' => ['confirmed', 'confirmed'],
            'ready' => ['ready', 'ready'],
            'delivered' => ['delivered', 'delivered'],
            'cancelled' => ['cancelled', 'cancelled'],
        ];
    }

    /** A line copies the name and the price of a dish of the soda of its order. */
    public function test_line_uses_a_dish_of_the_soda_of_its_order(): void
    {
        $order = OrderModel::factory()->create();

        $line = OrderLineModel::factory()->for($order, 'order')->create(['quantity' => 3]);
        $dish = DishModel::query()->findOrFail($line->dish_id);

        $this->assertSame($order->soda_id, $line->soda_id);
        $this->assertSame($order->soda_id, $dish->soda_id);
        $this->assertSame($dish->name, $line->dish_name);
        $this->assertSame($dish->price, $line->unit_price);
        $this->assertCount(1, $order->lines);
    }
}
