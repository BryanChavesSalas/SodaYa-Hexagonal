<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $soda_id
 * @property CarbonImmutable $closed_on
 * @property string|null $reason
 */
#[Table('closures')]
#[Fillable(['id', 'soda_id', 'closed_on', 'reason'])]
#[UseFactory(ClosureFactory::class)]
final class ClosureModel extends Model
{
    /** @use HasFactory<ClosureFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the closure date to an immutable calendar date.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_on' => 'immutable_date',
        ];
    }
}
