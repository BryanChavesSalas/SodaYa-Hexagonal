<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Catalog\Promotions\Application\Contracts\ActivePromotion;
use Src\Catalog\Promotions\Domain\Contracts\PromotionRepository;
use Src\Catalog\Promotions\Infrastructure\Persistence\Queries\EloquentActivePromotion;
use Src\Catalog\Promotions\Infrastructure\Persistence\Repositories\EloquentPromotionRepository;

final class PromotionsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PromotionRepository::class => EloquentPromotionRepository::class,
    ];

    /** One active-promotion reader per request, so the whole menu or order asks the database once per soda and minute. */
    public function register(): void
    {
        $this->app->scoped(ActivePromotion::class, EloquentActivePromotion::class);
    }
}
