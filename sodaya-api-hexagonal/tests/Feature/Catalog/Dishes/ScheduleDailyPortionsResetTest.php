<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Dishes;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\CommitsRows;
use Tests\TestCase;

final class ScheduleDailyPortionsResetTest extends TestCase
{
    use CommitsRows;

    private string $dishId;

    /** A soda ends the day with 2 portions of a dish whose base is 20. */
    protected function setUp(): void
    {
        parent::setUp();

        $soda = SodaModel::factory()->create();
        $this->dishId = $this->committedFor($soda->id, fn (): string => DishModel::factory()->create([
            'soda_id' => $soda->id,
            'available_portions' => 2,
            'base_portions' => 20,
        ])->id);
    }

    /** At 5:00 in Costa Rica the scheduler resets the portions; a minute earlier it does nothing. */
    public function test_portions_are_reset_at_five_in_the_morning_costa_rica_time(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 6, 4, 59, 0, 'America/Costa_Rica'));
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(2, $this->availablePortions());

        $this->travelTo(Carbon::create(2026, 10, 6, 5, 0, 0, 'America/Costa_Rica'));
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(20, $this->availablePortions());
    }

    /** 5:00 in Costa Rica is 11:00 UTC: the time is read in the zone of the business, not of the server. */
    public function test_reset_follows_costa_rica_time_and_not_utc(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 6, 5, 0, 0, 'UTC'));
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(2, $this->availablePortions());

        $this->travelTo(Carbon::create(2026, 10, 6, 11, 0, 0, 'UTC'));
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(20, $this->availablePortions());
    }

    /** While a previous run still holds the lock, the scheduler skips the reset. */
    public function test_reset_never_runs_twice_at_the_same_time(): void
    {
        $event = $this->resetEvent();
        $this->assertTrue($event->withoutOverlapping);

        $event->mutex->create($event);
        $this->travelTo(Carbon::create(2026, 10, 6, 5, 0, 0, 'America/Costa_Rica'));
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(2, $this->availablePortions());

        $event->mutex->forget($event);
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(20, $this->availablePortions());
    }

    /** The command can be run by hand too. */
    public function test_command_resets_the_portions(): void
    {
        $this->artisan('sodaya:reset-daily-portions')
            ->expectsOutputToContain('Las porciones de todas las sodas volvieron a su cantidad base.')
            ->assertSuccessful();

        $this->assertSame(20, $this->availablePortions());
    }

    /** The scheduled event of the reset. */
    private function resetEvent(): Event
    {
        $events = array_filter(
            app(Schedule::class)->events(),
            fn (Event $event): bool => str_contains((string) $event->command, 'sodaya:reset-daily-portions'),
        );

        $this->assertCount(1, $events);

        return array_values($events)[0];
    }

    /** Portions left of the dish, read with the maintenance role. */
    private function availablePortions(): int
    {
        return (int) DB::connection('pgsql_maintenance')->table('dishes')->where('id', $this->dishId)->value('available_portions');
    }
}
