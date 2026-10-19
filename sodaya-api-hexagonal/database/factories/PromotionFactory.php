<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfASoda;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Catalog\Promotions\Infrastructure\Persistence\Models\PromotionModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

/**
 * @extends Factory<PromotionModel>
 */
final class PromotionFactory extends Factory
{
    use CreatesRowsOfASoda;

    protected $model = PromotionModel::class;

    /**
     * Default state of a fictitious happy hour: Wednesday afternoon, 20 % off.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'soda_id' => SodaModel::factory(),
            'weekday' => 3,
            'starts_at' => '14:00',
            'ends_at' => '15:00',
            'percentage' => 20,
        ];
    }
}
