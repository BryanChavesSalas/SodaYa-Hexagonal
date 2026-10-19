<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\DTOs;

final readonly class PlaceOrderCommand
{
    /**
     * Carry who orders, from which soda and which dishes, with the name and price each dish has now.
     *
     * @param  list<array{dish_id: string, dish_name: string, unit_price: int, quantity: int}>  $lines
     */
    public function __construct(
        public string $customerId,
        public string $sodaId,
        public array $lines,
    ) {}
}
