<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Menu\Application\Contracts\PublicMenuReader;
use Src\Catalog\Menu\Application\DTOs\MenuDish;
use Src\Catalog\Menu\Application\DTOs\PublicMenu;
use Src\Shared\Domain\ValueObjects\SodaId;

/**
 * @phpstan-import-type PublicMenuRows from PublicMenu
 */
final readonly class CachedPublicMenuReader implements PublicMenuReader
{
    /** Seconds before the expiration during which a stale menu is served while it is rebuilt in the background. */
    private const int STALE_SECONDS = 60;

    /** Wrap the reader that goes to the database with the cache and the seconds a menu may stay in it. */
    public function __construct(
        private PublicMenuReader $inner,
        private CacheRepository $cache,
        private int $seconds,
    ) {}

    /**
     * Serve the menu from the cache, under the current version of the soda, and complete the portions
     * with one light query: they change with every order and are never cached.
     */
    public function menuOf(SodaId $sodaId): ?PublicMenu
    {
        $version = (int) $this->cache->get(MenuCacheKeys::version($sodaId->value), MenuCacheKeys::FIRST_VERSION);

        /** @var PublicMenuRows|null $rows */
        $rows = $this->cache->flexible(
            MenuCacheKeys::menu($sodaId->value, $version),
            [$this->seconds - self::STALE_SECONDS, $this->seconds],
            fn (): ?array => $this->inner->menuOf($sodaId)?->toArray(),
        );

        return $rows === null ? null : PublicMenu::fromArray($rows, $this->availablePortionsOf($sodaId));
    }

    /** Read one dish straight from the database: its detail is consulted far less than the menu. */
    public function dishOf(SodaId $sodaId, DishId $dishId): ?MenuDish
    {
        return $this->inner->dishOf($sodaId, $dishId);
    }

    /**
     * Portions every dish of the soda has right now, by dish identifier.
     *
     * @return array<string, int>
     */
    private function availablePortionsOf(SodaId $sodaId): array
    {
        /** @var array<string, int> $portions */
        $portions = DishModel::query()
            ->where('soda_id', $sodaId->value)
            ->pluck('available_portions', 'id')
            ->all();

        return $portions;
    }
}
