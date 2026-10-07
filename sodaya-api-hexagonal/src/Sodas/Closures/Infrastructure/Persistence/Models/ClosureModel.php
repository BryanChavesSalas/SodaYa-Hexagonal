<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Persistence\Models;

use Database\Factories\ClosureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use HasFactory<ClosureFactory>
 */
final class ClosureModel extends Model
{
    /** @use HasFactory<ClosureFactory> */
    use HasFactory;

    protected $table = 'closures';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'soda_id',
        'date',
        'reason',
    ];

    protected static function newFactory(): ClosureFactory
    {
        return ClosureFactory::new();
    }
}
