<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\DTOs;

final readonly class PlaceOrderCommand
{
    /**
     * Carry who orders, from which soda and how many portions of each dish; the name and the price come from the catalog.
     *
     * @param  list<array{dish_id: string, quantity: int}>  $lines
     */
    public function __construct(
        public string $customerId,
        public string $sodaId,
        public array $lines,
    ) {}
}
