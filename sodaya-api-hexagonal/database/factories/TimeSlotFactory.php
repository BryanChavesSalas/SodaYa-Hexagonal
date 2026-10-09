<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

/**
 * @extends Factory<TimeSlotModel>
 */
final class TimeSlotFactory extends Factory
{
    protected $model = TimeSlotModel::class;

    /**
     * Default state of a fictitious slot: Monday morning.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'soda_id' => SodaModel::factory(),
            'day_of_week' => 1,
            'opens_at' => '08:00',
            'closes_at' => '12:00',
        ];
    }
}
