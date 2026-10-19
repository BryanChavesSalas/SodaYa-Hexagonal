<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use Src\Ordering\Orders\Domain\Entities\OrderLine;
use Src\Shared\Domain\ValueObjects\SodaId;

interface PortionStock
{
    /**
     * Take from the menu of the soda the portions of every line, or none: answer false when a dish is missing,
     * inactive or without enough portions.
     *
     * @param  list<OrderLine>  $lines
     */
    public function reserve(SodaId $sodaId, array $lines): bool;

    /**
     * Give back to the menu of the soda the portions of the lines.
     *
     * @param  list<OrderLine>  $lines
     */
    public function release(SodaId $sodaId, array $lines): void;
}
