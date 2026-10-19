<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\DTOs;

final readonly class OrderableDish
{
    /** Describe a dish of the catalog as an order needs it: its name, the price it sells for right now, happy hour included, its minutes, and whether it is on the menu. */
    public function __construct(
        public string $id,
        public string $name,
        public int $unitPrice,
        public int $preparationMinutes,
        public bool $active,
    ) {}
}
