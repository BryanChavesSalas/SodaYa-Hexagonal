<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Catalog\Dishes\Infrastructure\DishesServiceProvider;
use Src\Catalog\Menu\Infrastructure\MenuServiceProvider;
use Src\Shared\Infrastructure\SharedServiceProvider;
use Src\Sodas\Schedules\Infrastructure\SchedulesServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    DishesServiceProvider::class,
    MenuServiceProvider::class,
    SchedulesServiceProvider::class,
];
