<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Menu;

use PHPUnit\Framework\Attributes\DataProvider;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class PublicMenuOpenStatusTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private SodaModel $soda;

    /** Create a soda that serves on Mondays from 08:00 to 12:00 and from 13:00 to 17:00. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->soda = SodaModel::factory()->create();

        $this->slot(1, '08:00', '12:00');
        $this->slot(1, '13:00', '17:00');
    }

    /** The soda opens on the opening minute and closes on the closing minute. */
    #[DataProvider('mondayMoments')]
    public function test_open_flag_follows_the_boundaries_of_each_slot(string $moment, bool $open): void
    {
        $this->travelTo($moment);

        $this->assertSame($open, $this->isOpen());
    }

    /**
     * Moments of Monday 2026-10-05 in Costa Rica and whether the soda is open.
     *
     * @return array<string, array{string, bool}>
     */
    public static function mondayMoments(): array
    {
        return [
            'last second before opening' => ['2026-10-05 07:59:59', false],
            'opening minute' => ['2026-10-05 08:00:00', true],
            'last second of the first slot' => ['2026-10-05 11:59:59', true],
            'closing minute' => ['2026-10-05 12:00:00', false],
            'between the two slots' => ['2026-10-05 12:59:59', false],
            'opening minute of the second slot' => ['2026-10-05 13:00:00', true],
            'closing minute of the second slot' => ['2026-10-05 17:00:00', false],
        ];
    }

    /** A day without slots and a soda without schedule are closed. */
    public function test_soda_is_closed_without_slots_for_the_day(): void
    {
        $this->travelTo('2026-10-06 10:00:00');

        $this->assertFalse($this->isOpen());
        $this->assertFalse($this->isOpen(SodaModel::factory()->create()));
    }

    /** An exceptional closure closes the soda during its opening hours, only on that date. */
    public function test_exceptional_closure_closes_the_soda_all_day(): void
    {
        ClosureModel::factory()->create(['soda_id' => $this->soda->id, 'closed_on' => '2026-10-05']);
        ClosureModel::factory()->create(['closed_on' => '2026-10-12']);

        $this->travelTo('2026-10-05 10:00:00');
        $this->assertFalse($this->isOpen());

        $this->travelTo('2026-10-12 10:00:00');
        $this->assertTrue($this->isOpen());
    }

    /** Closing for the rest of the day is reflected by the very next request. */
    public function test_closing_for_today_takes_effect_at_once(): void
    {
        $this->travelTo('2026-10-05 10:00:00');
        config(['sodaya.default_soda_id' => $this->soda->id]);

        $this->assertTrue($this->isOpen());

        $this->postJson('/api/v1/cocina/cierres/hoy', ['motivo' => 'Se acabó la comida'])->assertCreated();

        $this->assertFalse($this->isOpen());
    }

    /** The day and the time are those of Costa Rica, not those of UTC. */
    public function test_open_flag_uses_costa_rica_time(): void
    {
        $this->slot(1, '20:00', '23:30');
        ClosureModel::factory()->create(['soda_id' => $this->soda->id, 'closed_on' => '2026-10-06']);

        $this->travelTo('2026-10-05 23:00:00');

        $this->assertSame('2026-10-06 05:00', now()->utc()->format('Y-m-d H:i'));
        $this->assertTrue($this->isOpen());
    }

    /** Two requests around a boundary get different answers and none may be cached. */
    public function test_open_flag_is_computed_on_every_request(): void
    {
        $this->travelTo('2026-10-05 11:59:59');

        $this->getJson($this->endpoint($this->soda))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-cache, private')
            ->assertJsonPath('data.abierta', true);

        $this->travel(1)->second();

        $this->getJson($this->endpoint($this->soda))->assertOk()->assertJsonPath('data.abierta', false);
    }

    /** Read the open flag of the public menu, by default of the scenario soda. */
    private function isOpen(?SodaModel $soda = null): bool
    {
        return $this->getJson($this->endpoint($soda ?? $this->soda))->assertOk()->json('data.abierta');
    }

    /** Build the public menu endpoint of a soda. */
    private function endpoint(SodaModel $soda): string
    {
        return "/api/v1/sodas/{$soda->id}/platos";
    }

    /** Create a slot of the scenario soda. */
    private function slot(int $day, string $opensAt, string $closesAt): void
    {
        TimeSlotModel::factory()->create([
            'soda_id' => $this->soda->id,
            'day_of_week' => $day,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ]);
    }
}
