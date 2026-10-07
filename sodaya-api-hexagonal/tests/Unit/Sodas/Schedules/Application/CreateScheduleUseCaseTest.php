<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\Schedules\Application;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Schedules\Application\DTOs\CreateScheduleCommand;
use Src\Sodas\Schedules\Application\UseCases\CreateScheduleUseCase;
use Src\Sodas\Schedules\Domain\Exceptions\InvalidTimeRangeException;
use Src\Sodas\Schedules\Domain\Exceptions\ScheduleOverlapException;
use Tests\Support\Sodas\InMemoryScheduleRepository;

final class CreateScheduleUseCaseTest extends TestCase
{
    private const string SODA = '0198a1b2-c3d4-7e5f-8a6b-7c8d9e0f1a2b';

    private const string OTHER_SODA = '0198a1b2-c3d4-7e5f-8a6b-7c8d9e0f1a2c';

    private InMemoryScheduleRepository $repository;

    private CreateScheduleUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = new InMemoryScheduleRepository;
        $this->useCase = new CreateScheduleUseCase($this->repository);
    }

    private function command(string $opens, string $closes, int $day = 1, string $soda = self::SODA): CreateScheduleCommand
    {
        return new CreateScheduleCommand($soda, $day, $opens, $closes);
    }

    /** A valid slot is stored and returned. */
    public function test_creates_a_slot(): void
    {
        $schedule = $this->useCase->execute($this->command('07:00', '14:00', day: 3));

        $this->assertSame(3, $schedule->day->number);
        $this->assertSame('07:00', $schedule->opensAt->format());
        $this->assertSame('14:00', $schedule->closesAt->format());
        $this->assertSame([$schedule], $this->repository->allOf(new SodaId(self::SODA)));
    }

    /** A day may hold several slots that do not overlap. */
    public function test_allows_several_slots_in_a_day(): void
    {
        $this->useCase->execute($this->command('12:00', '15:00'));
        $this->useCase->execute($this->command('07:00', '12:00'));

        $slots = $this->repository->allOf(new SodaId(self::SODA));

        $this->assertSame(['07:00', '12:00'], array_map(fn ($slot): string => $slot->opensAt->format(), $slots));
    }

    /** An opening not before the closing is rejected and nothing is stored. */
    public function test_rejects_opening_not_before_closing(): void
    {
        $this->expectException(InvalidTimeRangeException::class);

        try {
            $this->useCase->execute($this->command('14:00', '14:00'));
        } finally {
            $this->assertSame([], $this->repository->allOf(new SodaId(self::SODA)));
        }
    }

    /** An overlap in the same day of the same soda is rejected. */
    public function test_rejects_overlap_in_same_day_and_soda(): void
    {
        $this->useCase->execute($this->command('08:00', '12:00'));

        $this->expectException(ScheduleOverlapException::class);

        try {
            $this->useCase->execute($this->command('11:00', '13:00'));
        } finally {
            $this->assertCount(1, $this->repository->allOf(new SodaId(self::SODA)));
        }
    }

    /** Another soda or another day may reuse the same hours. */
    public function test_isolates_by_soda_and_day(): void
    {
        $this->useCase->execute($this->command('08:00', '12:00'));
        $this->useCase->execute($this->command('08:00', '12:00', soda: self::OTHER_SODA));
        $this->useCase->execute($this->command('08:00', '12:00', day: 2));

        $this->assertCount(2, $this->repository->allOf(new SodaId(self::SODA)));
        $this->assertCount(1, $this->repository->allOf(new SodaId(self::OTHER_SODA)));
    }
}
