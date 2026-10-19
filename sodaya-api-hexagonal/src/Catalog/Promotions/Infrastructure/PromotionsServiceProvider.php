<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Catalog\Promotions\Domain\Contracts\PromotionRepository;
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
}
