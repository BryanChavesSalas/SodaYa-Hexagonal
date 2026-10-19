<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Domain\Entities;

use DateTimeImmutable;
use Src\Catalog\Promotions\Domain\ValueObjects\DiscountPercentage;
use Src\Catalog\Promotions\Domain\ValueObjects\PromotionId;
use Src\Catalog\Promotions\Domain\ValueObjects\TimeOfDay;
use Src\Catalog\Promotions\Domain\ValueObjects\TimeWindow;
use Src\Catalog\Promotions\Domain\ValueObjects\Weekday;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class Promotion
{
    /** Hold the full state of a happy hour: a discount for the whole soda on one day of the week, inside a window. */
    private function __construct(
        public PromotionId $id,
        public SodaId $sodaId,
        public Weekday $weekday,
        public TimeWindow $window,
        public DiscountPercentage $percentage,
    ) {}

    /** Define a new happy hour; the value objects already refused an inverted window or a percentage out of range. */
    public static function create(
        PromotionId $id,
        SodaId $sodaId,
        Weekday $weekday,
        TimeWindow $window,
        DiscountPercentage $percentage,
    ): self {
        return new self($id, $sodaId, $weekday, $window, $percentage);
    }

    /** Rebuild a happy hour from its stored state. */
    public static function reconstitute(
        PromotionId $id,
        SodaId $sodaId,
        Weekday $weekday,
        TimeWindow $window,
        DiscountPercentage $percentage,
    ): self {
        return new self($id, $sodaId, $weekday, $window, $percentage);
    }

    /** Tell whether the happy hour applies at a moment, read in its own time zone: the end minute is already out. */
    public function isActiveAt(DateTimeImmutable $moment): bool
    {
        return $this->weekday->equals(Weekday::fromMoment($moment))
            && $this->window->includes(TimeOfDay::fromMoment($moment));
    }

    /** Tell whether both happy hours belong to the same soda and day and share some minute. */
    public function overlaps(self $other): bool
    {
        return $this->sodaId->equals($other->sodaId)
            && $this->weekday->equals($other->weekday)
            && $this->window->overlaps($other->window);
    }
}
