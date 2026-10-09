<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Catalog\Categories\Infrastructure\CategoriesServiceProvider;
use Src\Catalog\Dishes\Infrastructure\DishesServiceProvider;
use Src\Catalog\Menu\Infrastructure\MenuServiceProvider;
use Src\Identity\Users\Infrastructure\UsersServiceProvider;
use Src\Shared\Infrastructure\SharedServiceProvider;
use Src\Sodas\Closures\Infrastructure\ClosuresServiceProvider;
use Src\Sodas\OpeningHours\Infrastructure\OpeningHoursServiceProvider;
use Src\Sodas\Profile\Infrastructure\ProfileServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    CategoriesServiceProvider::class,
    DishesServiceProvider::class,
    MenuServiceProvider::class,
    ClosuresServiceProvider::class,
    OpeningHoursServiceProvider::class,
    ProfileServiceProvider::class,
    UsersServiceProvider::class,
];
