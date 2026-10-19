<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use Src\Ordering\Orders\Application\DTOs\OrderableDish;
use Src\Shared\Domain\ValueObjects\SodaId;

interface OrderableDishes
{
    /**
     * Read, from the catalog of the soda, the dishes an order asks for; a dish of another soda is simply missing.
     *
     * @param  list<string>  $dishIds
     * @return array<string, OrderableDish> by dish identifier
     */
    public function ofSoda(SodaId $sodaId, array $dishIds): array;
}
