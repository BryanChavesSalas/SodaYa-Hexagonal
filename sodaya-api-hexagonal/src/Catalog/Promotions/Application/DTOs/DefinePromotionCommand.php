<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Application\DTOs;

final readonly class DefinePromotionCommand
{
    /** Carry the data needed to define a happy hour. */
    public function __construct(
        public string $sodaId,
        public int $weekday,
        public string $startsAt,
        public string $endsAt,
        public int $percentage,
    ) {}
}
