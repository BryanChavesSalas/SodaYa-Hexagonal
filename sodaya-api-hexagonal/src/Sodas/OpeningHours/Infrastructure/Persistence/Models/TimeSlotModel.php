<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Infrastructure\Persistence\Models;

use Database\Factories\TimeSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $soda_id
 * @property int $day_of_week
 * @property string $opens_at
 * @property string $closes_at
 */
#[Table('schedules')]
#[Fillable(['id', 'soda_id', 'day_of_week', 'opens_at', 'closes_at'])]
#[UseFactory(TimeSlotFactory::class)]
final class TimeSlotModel extends Model
{
    /** @use HasFactory<TimeSlotFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the day column to a native integer.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }
}
