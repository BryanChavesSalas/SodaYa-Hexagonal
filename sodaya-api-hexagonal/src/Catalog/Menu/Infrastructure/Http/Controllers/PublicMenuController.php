<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure\Http\Controllers;

use Src\Catalog\Menu\Application\UseCases\GetPublicDish;
use Src\Catalog\Menu\Application\UseCases\GetPublicMenu;
use Src\Catalog\Menu\Infrastructure\Http\Resources\MenuDishResource;
use Src\Catalog\Menu\Infrastructure\Http\Resources\PublicMenuResource;

final readonly class PublicMenuController
{
    /** Show the menu of a soda to any visitor. */
    public function index(string $soda, GetPublicMenu $getPublicMenu): PublicMenuResource
    {
        return new PublicMenuResource($getPublicMenu->execute($soda));
    }

    /** Show one dish of the menu to any visitor. */
    public function show(string $soda, string $plato, GetPublicDish $getPublicDish): MenuDishResource
    {
        return new MenuDishResource($getPublicDish->execute($soda, $plato));
    }
}
