<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Application\UseCases;

use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Domain\Contracts\TimeSlotRepository;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;

final readonly class ListTimeSlots
{
    /** Receive the time slot repository port. */
    public function __construct(private TimeSlotRepository $slots) {}

    /**
     * List one page of the schedule of the soda ordered by day and opening time.
     *
     * @return Page<TimeSlot>
     */
    public function execute(string $sodaId, PageRequest $page): Page
    {
        return $this->slots->pageOf(new SodaId($sodaId), $page);
    }
}
