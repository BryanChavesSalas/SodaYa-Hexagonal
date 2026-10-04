<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

/**
 * @extends Factory<SodaModel>
 */
final class SodaFactory extends Factory
{
    protected $model = SodaModel::class;

    /**
     * Default state of a fictitious soda.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Soda '.fake()->unique()->lastName(),
        ];
    }
}
