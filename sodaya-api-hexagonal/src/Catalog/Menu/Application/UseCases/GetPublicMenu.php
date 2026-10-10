<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\UseCases;

use DateTimeImmutable;
use Src\Catalog\Menu\Application\Contracts\PublicMenuReader;
use Src\Catalog\Menu\Application\Contracts\SodaOpenStatus;
use Src\Catalog\Menu\Application\DTOs\PublicMenu;
use Src\Catalog\Shared\Domain\Exceptions\SodaNotFoundException;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class GetPublicMenu
{
    /** Receive the menu read port and the port that knows whether the soda is open. */
    public function __construct(
        private PublicMenuReader $reader,
        private SodaOpenStatus $openStatus,
    ) {}

    /** Return the public menu of an existing soda with its open status at the moment. */
    public function execute(string $sodaId, DateTimeImmutable $moment): PublicMenu
    {
        $soda = new SodaId($sodaId);
        $menu = $this->reader->menuOf($soda) ?? throw SodaNotFoundException::create();

        return $menu->withOpenStatus($this->openStatus->isOpenAt($soda, $moment));
    }
}
