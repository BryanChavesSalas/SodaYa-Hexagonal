<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Billing\Payments\Infrastructure\PaymentsServiceProvider;
use Src\Catalog\Categories\Infrastructure\CategoriesServiceProvider;
use Src\Catalog\Dishes\Infrastructure\DishesServiceProvider;
use Src\Catalog\Menu\Infrastructure\MenuServiceProvider;
use Src\Catalog\Promotions\Infrastructure\PromotionsServiceProvider;
use Src\Identity\Users\Infrastructure\UsersServiceProvider;
use Src\Loyalty\Points\Infrastructure\PointsServiceProvider;
use Src\Ordering\Orders\Infrastructure\OrdersServiceProvider;
use Src\Reporting\Sales\Infrastructure\SalesServiceProvider;
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
    PromotionsServiceProvider::class,
    ClosuresServiceProvider::class,
    OpeningHoursServiceProvider::class,
    ProfileServiceProvider::class,
    UsersServiceProvider::class,
    OrdersServiceProvider::class,
    SalesServiceProvider::class,
    PointsServiceProvider::class,
    PaymentsServiceProvider::class,
];
