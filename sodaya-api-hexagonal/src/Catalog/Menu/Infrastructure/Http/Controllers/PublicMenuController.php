<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Src\Catalog\Menu\Application\UseCases\GetPublicDish;
use Src\Catalog\Menu\Application\UseCases\GetPublicMenu;
use Src\Catalog\Menu\Infrastructure\Http\Resources\MenuDishResource;
use Src\Catalog\Menu\Infrastructure\Http\Resources\PublicMenuResource;

#[Group('Menú público', 'Consulta del menú de una soda sin iniciar sesión.')]
final readonly class PublicMenuController
{
    /** Show the menu of a soda to any visitor. */
    #[Endpoint(title: 'Consultar el menú de una soda')]
    public function index(string $soda, GetPublicMenu $getPublicMenu): PublicMenuResource
    {
        return new PublicMenuResource($getPublicMenu->execute($soda));
    }

    /** Show one dish of the menu to any visitor. */
    #[Endpoint(title: 'Consultar un plato del menú')]
    public function show(string $soda, string $plato, GetPublicDish $getPublicDish): MenuDishResource
    {
        return new MenuDishResource($getPublicDish->execute($soda, $plato));
    }
}
