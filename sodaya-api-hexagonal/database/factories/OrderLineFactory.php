<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfASoda;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;

/**
 * @extends Factory<OrderLineModel>
 */
final class OrderLineFactory extends Factory
{
    use CreatesRowsOfASoda;

    protected $model = OrderLineModel::class;

    /**
     * Default state of a line: one portion of a new dish of the soda of the order, at its current name and price.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => OrderModel::factory(),
            'soda_id' => fn (array $attributes): string => OrderModel::query()->findOrFail($attributes['order_id'])->soda_id,
            'dish_id' => fn (array $attributes): string => DishModel::factory()->create(['soda_id' => $attributes['soda_id']])->id,
            'dish_name' => fn (array $attributes): string => $this->dish($attributes)->name,
            'unit_price' => fn (array $attributes): int => $this->dish($attributes)->price,
            'quantity' => 1,
        ];
    }

    /**
     * Dish the line points to.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function dish(array $attributes): DishModel
    {
        return DishModel::query()->findOrFail($attributes['dish_id']);
    }
}
