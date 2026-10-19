<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

final readonly class MenuCacheInvalidator
{
    /** Receive the cache that holds the versions. */
    public function __construct(private CacheRepository $cache) {}

    /** A row that shapes the menu was saved: the next reading rebuilds the menu of its soda. */
    public function saved(Model $model): void
    {
        $this->raiseVersionOf($model);
    }

    /** A row that shapes the menu was deleted: same effect. */
    public function deleted(Model $model): void
    {
        $this->raiseVersionOf($model);
    }

    /**
     * Raise the version of the menu of the soda the row belongs to; the keys of the old version stop being read
     * and expire on their own. The database store cannot increment a key that does not exist yet, so the first
     * version is written before incrementing it.
     */
    private function raiseVersionOf(Model $model): void
    {
        $sodaId = $model instanceof SodaModel ? $model->id : $model->getAttribute('soda_id');
        $key = MenuCacheKeys::version((string) $sodaId);

        $this->cache->add($key, MenuCacheKeys::FIRST_VERSION);
        $this->cache->increment($key);
    }
}
