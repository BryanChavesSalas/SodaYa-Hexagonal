<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

/**
 * @extends Factory<CategoryModel>
 */
final class CategoryFactory extends Factory
{
    protected $model = CategoryModel::class;

    /**
     * Default state of a fictitious category.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'soda_id' => SodaModel::factory(),
            'name' => ucfirst(fake()->unique()->word()),
        ];
    }
}
