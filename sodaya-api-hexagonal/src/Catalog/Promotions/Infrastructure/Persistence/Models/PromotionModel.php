<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Infrastructure\Persistence\Models;

use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $soda_id
 * @property int $weekday
 * @property string $starts_at
 * @property string $ends_at
 * @property int $percentage
 */
#[Table('promotions')]
#[Fillable(['id', 'soda_id', 'weekday', 'starts_at', 'ends_at', 'percentage'])]
#[UseFactory(PromotionFactory::class)]
final class PromotionModel extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the day and the percentage to native integers.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'percentage' => 'integer',
        ];
    }
}
