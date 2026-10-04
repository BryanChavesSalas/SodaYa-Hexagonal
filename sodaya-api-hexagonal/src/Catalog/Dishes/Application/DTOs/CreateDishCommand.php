<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Application\DTOs;

final readonly class CreateDishCommand
{
    /** Carry the data needed to register a dish. */
    public function __construct(
        public string $sodaId,
        public string $name,
        public ?string $description,
        public int $price,
        public int $preparationMinutes,
        public int $availablePortions,
        public ?string $categoryId,
    ) {}
}
