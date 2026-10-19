<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Src\Shared\Domain\Exceptions\NotFoundException;

interface SodaOfOrder
{
    /**
     * Soda of an order, as the transaction in course can see it: an order the transaction cannot see is not found.
     *
     * @throws NotFoundException
     */
    public function resolve(string $orderId): string;
}
