<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;

final class OpeningHoursSeeder extends Seeder
{
    /**
     * Fictitious weekly schedule: day of the week, opening and closing time.
     *
     * @var list<array{int, string, string}>
     */
    private const array SCHEDULE = [
        [1, '06:00', '14:00'],
        [1, '17:00', '21:00'],
        [2, '06:00', '14:00'],
        [2, '17:00', '21:00'],
        [3, '06:00', '14:00'],
        [3, '17:00', '21:00'],
        [4, '06:00', '14:00'],
        [4, '17:00', '21:00'],
        [5, '06:00', '14:00'],
        [5, '17:00', '21:00'],
        [6, '07:00', '15:00'],
    ];

    /** Seed the weekly schedule of the demo soda; Sunday stays closed. */
    public function run(): void
    {
        foreach (self::SCHEDULE as [$day, $opensAt, $closesAt]) {
            TimeSlotModel::query()->updateOrCreate(
                ['soda_id' => CatalogSeeder::DEMO_SODA_ID, 'day_of_week' => $day, 'opens_at' => $opensAt],
                ['closes_at' => $closesAt],
            );
        }
    }
}
