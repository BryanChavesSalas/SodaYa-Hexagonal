<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Catalog\Categories\Domain\Contracts\CategoryRepository;
use Src\Catalog\Categories\Infrastructure\Persistence\Repositories\EloquentCategoryRepository;

final class CategoriesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CategoryRepository::class => EloquentCategoryRepository::class,
    ];
}
