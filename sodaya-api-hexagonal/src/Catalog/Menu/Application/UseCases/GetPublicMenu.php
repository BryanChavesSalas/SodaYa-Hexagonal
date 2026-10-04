<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\UseCases;

use Src\Catalog\Menu\Application\Contracts\PublicMenuReader;
use Src\Catalog\Menu\Application\DTOs\PublicMenu;
use Src\Catalog\Shared\Domain\Exceptions\SodaNotFoundException;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class GetPublicMenu
{
    /** Receive the menu read port. */
    public function __construct(private PublicMenuReader $reader) {}

    /** Return the public menu of an existing soda. */
    public function execute(string $sodaId): PublicMenu
    {
        return $this->reader->menuOf(new SodaId($sodaId)) ?? throw SodaNotFoundException::create();
    }
}
