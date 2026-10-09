<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Infrastructure\Persistence\Models;

use Database\Factories\SodaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string|null $payment_account_id
 */
#[Table('sodas')]
#[Fillable(['id', 'name', 'payment_account_id'])]
#[UseFactory(SodaFactory::class)]
final class SodaModel extends Model
{
    /** @use HasFactory<SodaFactory> */
    use HasFactory, HasUuids;
}
