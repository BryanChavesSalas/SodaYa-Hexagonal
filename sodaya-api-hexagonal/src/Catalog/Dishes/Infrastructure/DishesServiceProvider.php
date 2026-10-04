<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Catalog\Dishes\Domain\Contracts\DishRepository;
use Src\Catalog\Dishes\Infrastructure\Persistence\Repositories\EloquentDishRepository;

final class DishesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        DishRepository::class => EloquentDishRepository::class,
    ];
}
