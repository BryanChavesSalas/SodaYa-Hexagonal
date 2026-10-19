<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\UseCases;

use DateTimeImmutable;
use Src\Catalog\Menu\Application\Contracts\PublicMenuReader;
use Src\Catalog\Menu\Application\Contracts\SodaOpenStatus;
use Src\Catalog\Menu\Application\DTOs\PublicMenu;
use Src\Catalog\Promotions\Application\Contracts\ActivePromotion;
use Src\Catalog\Shared\Domain\Exceptions\SodaNotFoundException;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class GetPublicMenu
{
    /** Receive the menu read port, the port that knows whether the soda is open and the one that knows its happy hour. */
    public function __construct(
        private PublicMenuReader $reader,
        private SodaOpenStatus $openStatus,
        private ActivePromotion $promotion,
    ) {}

    /**
     * Return the public menu of an existing soda with what the moment decides: whether it is open and the price
     * each dish sells for. The reader may serve the menu from a cache; what depends on the clock is added here.
     */
    public function execute(string $sodaId, DateTimeImmutable $moment): PublicMenu
    {
        $soda = new SodaId($sodaId);
        $menu = $this->reader->menuOf($soda) ?? throw SodaNotFoundException::create();

        return $menu
            ->withOpenStatus($this->openStatus->isOpenAt($soda, $moment))
            ->withDiscount($this->promotion->percentageFor($soda, $moment));
    }
}
