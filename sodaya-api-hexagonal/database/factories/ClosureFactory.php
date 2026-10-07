<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

/**
 * @extends Factory<ClosureModel>
 */
final class ClosureFactory extends Factory
{
    protected $model = ClosureModel::class;

    /**
     * Default state of a fictitious upcoming closure.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'soda_id' => SodaModel::factory(),
            'closed_on' => fake()->unique()->dateTimeBetween('+1 day', '+60 days')->format('Y-m-d'),
            'reason' => fake()->sentence(),
        ];
    }
}
