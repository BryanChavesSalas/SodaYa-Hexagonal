<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Application\DTOs;

final readonly class UpdateDishCommand
{
    /**
     * Carry the fields to change; absent keys keep their current value.
     *
     * @param array{
     *     name?: string,
     *     description?: string|null,
     *     price?: int,
     *     preparation_minutes?: int,
     *     available_portions?: int,
     *     category_id?: string|null,
     *     active?: bool,
     * } $changes
     */
    public function __construct(
        public string $dishId,
        public string $sodaId,
        public array $changes,
    ) {}
}
