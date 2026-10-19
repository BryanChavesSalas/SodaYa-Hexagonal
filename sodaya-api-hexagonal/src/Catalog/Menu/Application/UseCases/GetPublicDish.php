<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\UseCases;

use DateTimeImmutable;
use Src\Catalog\Dishes\Domain\Exceptions\DishNotFoundException;
use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Catalog\Menu\Application\Contracts\PublicMenuReader;
use Src\Catalog\Menu\Application\DTOs\MenuDish;
use Src\Catalog\Promotions\Application\Contracts\ActivePromotion;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class GetPublicDish
{
    /** Receive the menu read port and the port that knows the happy hour of the soda. */
    public function __construct(
        private PublicMenuReader $reader,
        private ActivePromotion $promotion,
    ) {}

    /** Return an active dish of the soda as visitors see it, with the price it sells for at the moment. */
    public function execute(string $sodaId, string $dishId, DateTimeImmutable $moment): MenuDish
    {
        $soda = new SodaId($sodaId);
        $dish = $this->reader->dishOf($soda, new DishId($dishId)) ?? throw DishNotFoundException::create();

        return $dish->withDiscount($this->promotion->percentageFor($soda, $moment));
    }
}
