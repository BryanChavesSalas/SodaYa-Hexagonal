<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Application\DTOs;

final readonly class RenameCategoryCommand
{
    public function __construct(
        public string $categoryId,
        public string $sodaId,
        public string $name,
    ) {}
}
