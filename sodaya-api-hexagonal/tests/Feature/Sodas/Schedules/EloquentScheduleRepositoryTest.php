<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\Schedules;

use Illuminate\Support\Str;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Src\Sodas\Schedules\Domain\Entities\Schedule;
use Src\Sodas\Schedules\Domain\Exceptions\ScheduleOverlapException;
use Src\Sodas\Schedules\Domain\Exceptions\ScheduleSodaNotFoundException;
use Src\Sodas\Schedules\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\Schedules\Domain\ValueObjects\TimeOfDay;
use Src\Sodas\Schedules\Infrastructure\Persistence\Repositories\EloquentScheduleRepository;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class EloquentScheduleRepositoryTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private EloquentScheduleRepository $repository;

    private SodaId $sodaId;

    /** Create the repository and the soda every scenario works on. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentScheduleRepository;
        $this->sodaId = new SodaId(SodaModel::factory()->create()->id);
    }

    /** A saved slot is read back with the same state. */
    public function test_saved_slot_is_read_back_unchanged(): void
    {
        $slot = $this->slot($this->sodaId, 3, '07:30', '14:00');

        $this->repository->save($slot);

        $this->assertEquals([$slot], $this->repository->allOf($this->sodaId));
    }

    /** Slots are listed by day and then opening time, only for the requested soda. */
    public function test_lists_ordered_slots_of_the_soda_only(): void
    {
        $this->repository->save($this->slot($this->sodaId, 2, '08:00', '12:00'));
        $this->repository->save($this->slot($this->sodaId, 1, '13:00', '17:00'));
        $this->repository->save($this->slot($this->sodaId, 1, '07:00', '12:00'));
        $otherSoda = new SodaId(SodaModel::factory()->create()->id);
        $this->repository->save($this->slot($otherSoda, 1, '06:00', '10:00'));

        $listed = array_map(
            fn (Schedule $slot): string => $slot->day->number.' '.$slot->opensAt->format(),
            $this->repository->allOf($this->sodaId),
        );

        $this->assertSame(['1 07:00', '1 13:00', '2 08:00'], $listed);
    }

    /** The exclusion violation becomes a domain overlap error and leaves no row behind. */
    public function test_overlap_is_translated_to_a_domain_exception(): void
    {
        $this->repository->save($this->slot($this->sodaId, 1, '08:00', '12:00'));

        try {
            $this->repository->save($this->slot($this->sodaId, 1, '11:00', '13:00'));
            $this->fail('An overlapping slot was stored.');
        } catch (ScheduleOverlapException $exception) {
            $this->assertSame('schedules.overlap', $exception->translationKey());
        }

        $this->assertDatabaseCount('schedules', 1);
    }

    /** Adjacent slots and the same hours on another soda are stored. */
    public function test_adjacent_and_foreign_slots_are_stored(): void
    {
        $this->repository->save($this->slot($this->sodaId, 1, '08:00', '12:00'));
        $this->repository->save($this->slot($this->sodaId, 1, '12:00', '15:00'));
        $this->repository->save($this->slot(new SodaId(SodaModel::factory()->create()->id), 1, '08:00', '12:00'));

        $this->assertDatabaseCount('schedules', 3);
    }

    /** A soda that does not exist is reported as such. */
    public function test_unknown_soda_is_translated_to_a_not_found_exception(): void
    {
        $this->expectException(ScheduleSodaNotFoundException::class);

        $this->repository->save($this->slot(new SodaId((string) Str::uuid7()), 1, '08:00', '12:00'));
    }

    /** Build a slot with a fresh identity. */
    private function slot(SodaId $sodaId, int $day, string $opens, string $closes): Schedule
    {
        return new Schedule(
            $this->repository->nextId(),
            $sodaId,
            new DayOfWeek($day),
            new TimeOfDay($opens),
            new TimeOfDay($closes),
        );
    }
}
