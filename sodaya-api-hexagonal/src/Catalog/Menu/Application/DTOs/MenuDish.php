<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\DTOs;

use DateTimeImmutable;

final readonly class MenuDish
{
    /** Describe a dish as a visitor sees it. */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public int $currentPrice,
        public int $preparationMinutes,
        public int $availablePortions,
        public DateTimeImmutable $updatedAt,
    ) {}

    /** Tell whether no portions are left to sell. */
    public function isSoldOut(): bool
    {
        return $this->availablePortions === 0;
    }
}
