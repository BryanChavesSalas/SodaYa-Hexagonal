<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

/**
 * @extends Factory<DishModel>
 */
final class DishFactory extends Factory
{
    protected $model = DishModel::class;

    /**
     * Default state of a fictitious dish.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'soda_id' => SodaModel::factory(),
            'category_id' => null,
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(10, 60) * 100,
            'preparation_minutes' => fake()->numberBetween(5, 30),
            'available_portions' => fake()->numberBetween(1, 40),
            'is_active' => true,
        ];
    }

    /** Mark the dish as taken off the menu. */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** Leave the dish without portions. */
    public function soldOut(): static
    {
        return $this->state(['available_portions' => 0]);
    }
}
