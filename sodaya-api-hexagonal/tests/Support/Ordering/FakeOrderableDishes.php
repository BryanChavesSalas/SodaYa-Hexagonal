<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\OrderableDishes;
use Src\Ordering\Orders\Application\DTOs\OrderableDish;
use Src\Shared\Domain\ValueObjects\SodaId;

final class FakeOrderableDishes implements OrderableDishes
{
    /** @param  array<string, OrderableDish>  $dishes  the catalog of the soda of the scenario, by dish identifier */
    public function __construct(private array $dishes = []) {}

    /** Answer the requested dishes the scenario knows, as the catalog would at any moment. */
    public function ofSoda(SodaId $sodaId, array $dishIds, DateTimeImmutable $now): array
    {
        return array_filter($this->dishes, fn (string $id): bool => in_array($id, $dishIds, true), ARRAY_FILTER_USE_KEY);
    }
}
