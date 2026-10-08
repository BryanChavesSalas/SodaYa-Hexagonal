<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
final class ScheduleModel extends Model
{
    use HasUuids;

    /**
     * Cast the day to a native integer.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['day_of_week' => 'integer'];
    }
}
