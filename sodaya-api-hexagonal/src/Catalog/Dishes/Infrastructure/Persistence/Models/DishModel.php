<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure\Persistence\Models;

use Database\Factories\DishFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $soda_id
 * @property string|null $category_id
 * @property string $name
 * @property string|null $description
 * @property int $price
 * @property int $preparation_minutes
 * @property int $available_portions
 * @property bool $is_active
 */
#[Table('dishes')]
#[Fillable([
    'id',
    'soda_id',
    'category_id',
    'name',
    'description',
    'price',
    'preparation_minutes',
    'available_portions',
    'is_active',
])]
#[UseFactory(DishFactory::class)]
final class DishModel extends Model
{
    /** @use HasFactory<DishFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the numeric and boolean columns to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'preparation_minutes' => 'integer',
            'available_portions' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
