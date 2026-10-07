<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;

/**
 * @extends Factory<ClosureModel>
 */
class ClosureFactory extends Factory
{
    protected $model = ClosureModel::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'soda_id' => (string) Str::uuid7(),
            'date' => CarbonImmutable::now('America/Costa_Rica')->format('Y-m-d'),
            'reason' => $this->faker->sentence(),
        ];
    }
}
