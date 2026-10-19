<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure\Cache;

final readonly class MenuCacheKeys
{
    /** Version a soda whose owner never edited the menu is at. */
    public const int FIRST_VERSION = 1;

    /** Key that holds the current version of the menu of a soda. */
    public static function version(string $sodaId): string
    {
        return "menu:{$sodaId}:version";
    }

    /** Key that holds one version of the menu of a soda; the soda isolates, the version invalidates. */
    public static function menu(string $sodaId, int $version): string
    {
        return "menu:{$sodaId}:v{$version}";
    }
}
