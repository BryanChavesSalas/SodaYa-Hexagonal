<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Application\DTOs;

final readonly class CreateCategoryCommand
{
    /** Carry the data needed to register a category. */
    public function __construct(
        public string $sodaId,
        public string $name,
    ) {}
}
