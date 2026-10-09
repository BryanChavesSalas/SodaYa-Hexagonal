<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\OpeningHours;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;
use Src\Sodas\OpeningHours\Domain\Exceptions\TimeSlotOverlapsException;
use Src\Sodas\OpeningHours\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeOfDay;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Repositories\EloquentTimeSlotRepository;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class EloquentTimeSlotRepositoryTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private EloquentTimeSlotRepository $repository;

    private SodaId $sodaId;

    /** Create the repository and the soda every scenario works on. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentTimeSlotRepository;
        $this->sodaId = new SodaId(SodaModel::factory()->create()->id);
    }

    /** A saved slot is read back with the same state. */
    public function test_saved_slot_is_read_back_unchanged(): void
    {
        $slot = $this->slot(1, '08:00', '12:00');

        $this->repository->save($slot);

        $this->assertEquals($slot, $this->repository->find($slot->id, $this->sodaId));
    }

    /** A slot is invisible when searched from another soda. */
    public function test_slot_of_another_soda_is_not_found(): void
    {
        $slot = $this->slot(1, '08:00', '12:00');
        $this->repository->save($slot);

        $otherSodaId = new SodaId(SodaModel::factory()->create()->id);

        $this->assertNull($this->repository->find($slot->id, $otherSodaId));
    }

    /** The listing follows the week and the clock and is limited to the soda. */
    public function test_listing_is_ordered_by_day_and_opening_time(): void
    {
        $this->repository->save($this->slot(2, '08:00', '12:00'));
        $this->repository->save($this->slot(1, '13:00', '17:00'));
        $this->repository->save($this->slot(1, '08:00', '12:00'));
        TimeSlotModel::factory()->create();

        $this->assertSame(
            ['1 08:00', '1 13:00', '2 08:00'],
            $this->describe($this->repository->allOf($this->sodaId)),
        );
    }

    /** Only the slots of the requested day are returned. */
    public function test_slots_of_a_day_exclude_the_other_days(): void
    {
        $this->repository->save($this->slot(1, '13:00', '17:00'));
        $this->repository->save($this->slot(1, '08:00', '12:00'));
        $this->repository->save($this->slot(2, '08:00', '12:00'));

        $this->assertSame(
            ['1 08:00', '1 13:00'],
            $this->describe($this->repository->ofDay($this->sodaId, new DayOfWeek(1))),
        );
    }

    /** The exclusion constraint surfaces as the domain error and keeps the transaction usable. */
    public function test_overlapping_slot_raises_the_domain_error(): void
    {
        $this->repository->save($this->slot(1, '08:00', '12:00'));

        try {
            $this->repository->save($this->slot(1, '11:00', '15:00'));
            $this->fail('An overlapping slot was accepted.');
        } catch (TimeSlotOverlapsException) {
            $this->assertDatabaseCount('schedules', 1);
        }
    }

    /** A deleted slot is gone from the schedule. */
    public function test_deleted_slot_is_removed(): void
    {
        $slot = $this->slot(1, '08:00', '12:00');
        $this->repository->save($slot);

        $this->repository->delete($slot);

        $this->assertSame([], $this->repository->allOf($this->sodaId));
    }

    /** Build a slot of the scenario soda with a fresh identity. */
    private function slot(int $day, string $opensAt, string $closesAt): TimeSlot
    {
        return TimeSlot::create(
            $this->repository->nextId(),
            $this->sodaId,
            new DayOfWeek($day),
            new TimeOfDay($opensAt),
            new TimeOfDay($closesAt),
        );
    }

    /**
     * Summarize slots as "day opening" strings.
     *
     * @param  list<TimeSlot>  $slots
     * @return list<string>
     */
    private function describe(array $slots): array
    {
        return array_map(fn (TimeSlot $slot): string => "{$slot->day->value} {$slot->opensAt->value}", $slots);
    }
}
