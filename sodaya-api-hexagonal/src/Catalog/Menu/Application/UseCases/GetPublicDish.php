<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\UseCases;

use Src\Catalog\Dishes\Domain\Exceptions\DishNotFoundException;
use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Catalog\Menu\Application\Contracts\PublicMenuReader;
use Src\Catalog\Menu\Application\DTOs\MenuDish;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class GetPublicDish
{
    /** Receive the menu read port. */
    public function __construct(private PublicMenuReader $reader) {}

    /** Return an active dish of the soda as visitors see it. */
    public function execute(string $sodaId, string $dishId): MenuDish
    {
        return $this->reader->dishOf(new SodaId($sodaId), new DishId($dishId)) ?? throw DishNotFoundException::create();
    }
}
