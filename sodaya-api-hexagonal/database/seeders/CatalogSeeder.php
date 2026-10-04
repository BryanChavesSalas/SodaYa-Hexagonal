<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

final class CatalogSeeder extends Seeder
{
    public const string DEMO_SODA_ID = '0192f0c4-0000-7000-8000-000000000001';

    /**
     * Fictitious menu grouped by category: name, price, minutes and portions.
     *
     * @var array<string, list<array{string, int, int, int}>>
     */
    private const array MENU = [
        'Desayunos' => [
            ['Gallo pinto con huevo', 2_200, 10, 25],
            ['Pinto con natilla y maduro', 2_500, 10, 20],
        ],
        'Casados' => [
            ['Casado de pollo', 2_800, 15, 30],
            ['Casado de pescado', 3_500, 20, 12],
            ['Casado de bistec encebollado', 3_800, 20, 15],
        ],
        'Bebidas' => [
            ['Fresco de cas', 900, 5, 40],
            ['Café chorreado', 800, 5, 50],
        ],
    ];

    /** Seed a demo soda with its categories and dishes. */
    public function run(): void
    {
        $soda = SodaModel::query()->updateOrCreate(
            ['id' => self::DEMO_SODA_ID],
            ['name' => 'Soda La Esquina'],
        );

        foreach (self::MENU as $categoryName => $dishes) {
            $category = CategoryModel::query()->updateOrCreate(
                ['soda_id' => $soda->id, 'name' => $categoryName],
            );

            foreach ($dishes as [$name, $price, $minutes, $portions]) {
                DishModel::query()->updateOrCreate(
                    ['soda_id' => $soda->id, 'name' => $name],
                    [
                        'category_id' => $category->id,
                        'price' => $price,
                        'preparation_minutes' => $minutes,
                        'available_portions' => $portions,
                    ],
                );
            }
        }

        DishModel::query()->updateOrCreate(
            ['soda_id' => $soda->id, 'name' => 'Chifrijo'],
            ['price' => 3_000, 'preparation_minutes' => 15, 'available_portions' => 0],
        );
    }
}
