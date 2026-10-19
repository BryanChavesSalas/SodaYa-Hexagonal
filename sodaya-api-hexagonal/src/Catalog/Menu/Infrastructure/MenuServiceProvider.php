<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Menu\Application\Contracts\PublicMenuReader;
use Src\Catalog\Menu\Application\Contracts\SodaOpenStatus;
use Src\Catalog\Menu\Infrastructure\Cache\CachedPublicMenuReader;
use Src\Catalog\Menu\Infrastructure\Cache\MenuCacheInvalidator;
use Src\Catalog\Menu\Infrastructure\Persistence\Queries\EloquentPublicMenuReader;
use Src\Catalog\Menu\Infrastructure\Sodas\OpeningHoursSodaOpenStatus;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

final class MenuServiceProvider extends ServiceProvider
{
    /**
     * Models whose rows shape the public menu: saving or deleting one raises the version of the menu of its soda.
     *
     * @var list<class-string<Model>>
     */
    private const array OBSERVED_MODELS = [
        DishModel::class,
        CategoryModel::class,
        TimeSlotModel::class,
        ClosureModel::class,
        SodaModel::class,
    ];

    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SodaOpenStatus::class => OpeningHoursSodaOpenStatus::class,
    ];

    /** Serve the menu through the cache decorator; taking the cache away is taking this binding away. */
    public function register(): void
    {
        $this->app->bind(PublicMenuReader::class, fn (Application $app): PublicMenuReader => new CachedPublicMenuReader(
            $app->make(EloquentPublicMenuReader::class),
            $app->make(CacheRepository::class),
            (int) config('sodaya.menu_cache_seconds'),
        ));
    }

    /** Invalidate the cached menu of a soda whenever one of the rows that shape it changes. */
    public function boot(): void
    {
        foreach (self::OBSERVED_MODELS as $model) {
            $model::observe(MenuCacheInvalidator::class);
        }
    }
}
