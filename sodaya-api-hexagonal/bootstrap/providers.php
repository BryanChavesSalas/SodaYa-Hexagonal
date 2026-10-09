<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Catalog\Categories\Infrastructure\CategoriesServiceProvider;
use Src\Catalog\Dishes\Infrastructure\DishesServiceProvider;
use Src\Catalog\Menu\Infrastructure\MenuServiceProvider;
use Src\Shared\Infrastructure\SharedServiceProvider;
use Src\Sodas\Closures\Infrastructure\ClosuresServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    CategoriesServiceProvider::class,
    DishesServiceProvider::class,
    MenuServiceProvider::class,
    ClosuresServiceProvider::class,
];
