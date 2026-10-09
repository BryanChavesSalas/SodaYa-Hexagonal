<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\OpeningHours;

use PHPUnit\Framework\Attributes\DataProvider;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ManageOpeningHoursTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/cocina/horario';

    /** Make a fresh soda the current tenant. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda();
    }

    /** The schedule is ordered by day and opening time and excludes other sodas. */
    public function test_owner_sees_the_schedule_in_order(): void
    {
        $tuesday = $this->slot(2, '08:00', '12:00');
        $mondayAfternoon = $this->slot(1, '13:00', '17:00');
        $mondayMorning = $this->slot(1, '08:00', '12:00');
        TimeSlotModel::factory()->create();

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $mondayMorning->id, 'dia' => 1, 'abre' => '08:00', 'cierra' => '12:00'],
                ['id' => $mondayAfternoon->id, 'dia' => 1, 'abre' => '13:00', 'cierra' => '17:00'],
                ['id' => $tuesday->id, 'dia' => 2, 'abre' => '08:00', 'cierra' => '12:00'],
            ]]);
    }

    /** A valid slot is stored for the soda and returned. */
    public function test_owner_adds_a_slot(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->payload());

        $slotId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertExactJson(['data' => ['id' => $slotId, 'dia' => 1, 'abre' => '08:00', 'cierra' => '12:00']]);

        $this->assertDatabaseHas('schedules', [
            'id' => $slotId,
            'soda_id' => $this->soda->id,
            'day_of_week' => 1,
            'opens_at' => '08:00:00',
            'closes_at' => '12:00:00',
        ]);
    }

    /** A day accepts a second slot that starts when the first one closes. */
    public function test_day_accepts_a_slot_that_touches_another(): void
    {
        $this->slot(1, '08:00', '12:00');

        $this->postJson(self::ENDPOINT, $this->payload(['abre' => '12:00', 'cierra' => '15:00']))->assertCreated();

        $this->assertDatabaseCount('schedules', 2);
    }

    /** Monday and Sunday are the first and the last accepted days. */
    public function test_week_runs_from_monday_to_sunday(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['dia' => 1]))->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload(['dia' => 7]))->assertCreated();
    }

    /** Invalid fields are reported one by one in Spanish. */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_data_is_rejected(string $field, mixed $value, string $message): void
    {
        $this->postJson(self::ENDPOINT, $this->payload([$field => $value]))
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonPath("errores.{$field}.0", $message);

        $this->assertDatabaseCount('schedules', 0);
    }

    /**
     * Invalid values for a field and the message each one produces.
     *
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing day' => ['dia', null, 'El campo día es obligatorio.'],
            'day before monday' => ['dia', 0, 'El campo día debe estar entre 1 y 7.'],
            'day after sunday' => ['dia', 8, 'El campo día debe estar entre 1 y 7.'],
            'opening without format' => ['abre', '8am', 'El campo abre debe tener el formato H:i.'],
            'closing with seconds' => ['cierra', '12:00:00', 'El campo cierra debe tener el formato H:i.'],
            'closing equal to opening' => ['cierra', '08:00', 'La hora de cierre debe ser posterior a la de apertura.'],
            'closing before opening' => ['cierra', '07:59', 'La hora de cierre debe ser posterior a la de apertura.'],
        ];
    }

    /** A slot that shares a minute with another of the same day answers a conflict. */
    public function test_overlapping_slot_answers_a_conflict(): void
    {
        $this->slot(1, '08:00', '12:00');

        $this->postJson(self::ENDPOINT, $this->payload(['abre' => '11:59', 'cierra' => '15:00']))
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'http://localhost/problemas/conflicto')
            ->assertJsonPath('detail', 'La franja se traslapa con otra del mismo día.');

        $this->assertDatabaseCount('schedules', 1);
    }

    /** The same hours are free on another day and for another soda. */
    public function test_same_hours_are_accepted_on_another_day_or_soda(): void
    {
        $this->slot(2, '08:00', '12:00');
        TimeSlotModel::factory()->create(['day_of_week' => 1]);

        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
    }

    /** The owner removes a slot from the schedule. */
    public function test_owner_deletes_a_slot(): void
    {
        $slot = $this->slot(1, '08:00', '12:00');

        $this->deleteJson(self::ENDPOINT.'/'.$slot->id)->assertNoContent();

        $this->assertDatabaseCount('schedules', 0);
    }

    /** Missing slots and slots of other sodas answer 404 and stay in place. */
    public function test_missing_and_foreign_slots_are_not_found(): void
    {
        $foreign = TimeSlotModel::factory()->create();

        foreach ([$foreign->id, '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99'] as $slotId) {
            $this->deleteJson(self::ENDPOINT.'/'.$slotId)
                ->assertNotFound()
                ->assertJsonPath('detail', 'La franja no existe.');
        }

        $this->assertDatabaseHas('schedules', ['id' => $foreign->id]);
    }

    /** Create a slot of the current soda. */
    private function slot(int $day, string $opensAt, string $closesAt): TimeSlotModel
    {
        return TimeSlotModel::factory()->create([
            'soda_id' => $this->soda->id,
            'day_of_week' => $day,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ]);
    }

    /**
     * Build a valid payload with optional overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return ['dia' => 1, 'abre' => '08:00', 'cierra' => '12:00', ...$overrides];
    }
}
