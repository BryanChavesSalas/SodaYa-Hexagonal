<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Application\DTOs;

final readonly class ChangeSodaLogoCommand
{
    /** Carry the soda and its new logo, already validated by its content. */
    public function __construct(
        public string $sodaId,
        public string $imagePath,
        public string $extension,
    ) {}
}
