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
    #[Endpoint(
        title: 'Consultar el menú de una soda',
        description: 'El atributo `abierta` indica si la soda atiende en el momento de la respuesta, según su horario y sus cierres excepcionales, en hora de Costa Rica. Cada plato trae su `precio` base y su `precio_vigente`, que aplica la hora feliz activa en ese momento; cuando no hay promoción, los dos coinciden.',
    )]
    public function index(string $soda, GetPublicMenu $getPublicMenu): PublicMenuResource
    {
        return new PublicMenuResource($getPublicMenu->execute($soda, now()->toDateTimeImmutable()));
    }

    /** Show one dish of the menu to any visitor. */
    #[Endpoint(title: 'Consultar un plato del menú', description: 'El `precio_vigente` aplica la hora feliz activa en el momento de la respuesta.')]
    public function show(string $soda, string $plato, GetPublicDish $getPublicDish): MenuDishResource
    {
        return new MenuDishResource($getPublicDish->execute($soda, $plato, now()->toDateTimeImmutable()));
    }
}
