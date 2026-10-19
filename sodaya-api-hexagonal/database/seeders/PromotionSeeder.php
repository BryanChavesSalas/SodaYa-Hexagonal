<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Catalog\Promotions\Infrastructure\Persistence\Models\PromotionModel;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;

final class PromotionSeeder extends Seeder
{
    /**
     * Fictitious happy hours: day of the week, start, end and percentage.
     *
     * @var list<array{int, string, string, int}>
     */
    private const array HAPPY_HOURS = [
        [3, '14:00', '15:00', 20],
        [5, '17:00', '18:00', 10],
    ];

    /** Seed the happy hours of the demo soda in a transaction scoped to it. */
    public function run(SodaTransaction $transaction): void
    {
        $transaction->run(CatalogSeeder::DEMO_SODA_ID, function (): void {
            foreach (self::HAPPY_HOURS as [$weekday, $startsAt, $endsAt, $percentage]) {
                PromotionModel::query()->updateOrCreate(
                    ['soda_id' => CatalogSeeder::DEMO_SODA_ID, 'weekday' => $weekday, 'starts_at' => $startsAt],
                    ['ends_at' => $endsAt, 'percentage' => $percentage],
                );
            }
        });
    }
}
