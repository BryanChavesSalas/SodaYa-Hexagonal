<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Infrastructure\Persistence\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 */
#[Table('categories')]
#[Fillable(['id', 'soda_id', 'name'])]
#[UseFactory(CategoryFactory::class)]
final class CategoryModel extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUuids;
}
