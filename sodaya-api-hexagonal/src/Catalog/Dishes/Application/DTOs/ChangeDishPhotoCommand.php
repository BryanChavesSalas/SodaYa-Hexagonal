<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Application\DTOs;

final readonly class ChangeDishPhotoCommand
{
    /** Carry the dish, its soda and the uploaded image already validated by its content. */
    public function __construct(
        public string $dishId,
        public string $sodaId,
        public string $imagePath,
        public string $extension,
    ) {}
}
