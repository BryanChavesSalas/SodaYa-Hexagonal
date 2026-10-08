<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\Schedules;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Domain\Enums\Role;
use Src\Identity\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ScheduleHttpTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/horarios';

    /** Make a fresh soda the current tenant and sign in as its owner. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda();

        Sanctum::actingAs(UserModel::factory()->owner($this->soda->id)->create(), Role::Owner->tokenAbilities());
    }

    /** A valid slot is stored for the current soda and returned. */
    public function test_owner_creates_a_slot(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->payload());

        $response
            ->assertCreated()
            ->assertExactJson(['data' => [
                'id' => $response->json('data.id'),
                'dia_semana' => 1,
                'apertura' => '08:00',
                'cierre' => '12:00',
            ]]);

        $this->assertDatabaseHas('schedules', ['soda_id' => $this->soda->id, 'day_of_week' => 1]);
    }

    /** The soda sent in the payload is ignored. */
    public function test_soda_sent_in_the_payload_is_ignored(): void
    {
        $otherSoda = SodaModel::factory()->create();

        $this->postJson(self::ENDPOINT, $this->payload(['soda_id' => $otherSoda->id]))->assertCreated();

        $this->assertDatabaseHas('schedules', ['soda_id' => $this->soda->id]);
        $this->assertDatabaseMissing('schedules', ['soda_id' => $otherSoda->id]);
    }

    /** An opening not before the closing answers 422 with a Spanish detail. */
    #[DataProvider('invertedRanges')]
    public function test_opening_not_before_closing_is_unprocessable(string $opens, string $closes): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['apertura' => $opens, 'cierre' => $closes]))
            ->assertUnprocessable()
            ->assertJsonPath('detail', 'La hora de apertura debe ser anterior a la de cierre.');

        $this->assertDatabaseCount('schedules', 0);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invertedRanges(): array
    {
        return ['equal' => ['12:00', '12:00'], 'reversed' => ['18:00', '08:00']];
    }

    /** Malformed fields are reported one by one in Spanish. */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_data_is_rejected(string $field, mixed $value, string $message): void
    {
        $this->postJson(self::ENDPOINT, $this->payload([$field => $value]))
            ->assertUnprocessable()
            ->assertJsonPath("errores.{$field}.0", $message);
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing day' => ['dia_semana', null, 'El campo día de la semana es obligatorio.'],
            'day above sunday' => ['dia_semana', 8, 'El campo día de la semana debe estar entre 1 y 7.'],
            'malformed opening' => ['apertura', '8am', 'El formato del campo apertura no es válido.'],
            'hour out of range' => ['cierre', '24:00', 'El formato del campo cierre no es válido.'],
        ];
    }

    /** An overlap in the same day answers 409 in Spanish. */
    public function test_overlap_is_a_conflict(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        $this->postJson(self::ENDPOINT, $this->payload(['apertura' => '11:00', 'cierre' => '13:00']))
            ->assertConflict()
            ->assertJsonPath('detail', 'La franja se traslapa con otra del mismo día.');

        $this->assertDatabaseCount('schedules', 1);
    }

    /** Adjacent slots and other days are accepted. */
    public function test_adjacent_slots_and_other_days_are_accepted(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload(['apertura' => '12:00', 'cierre' => '15:00']))->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload(['dia_semana' => 2]))->assertCreated();
    }

    /** A soda that does not exist answers 404. */
    public function test_unknown_soda_is_not_found(): void
    {
        config(['sodaya.default_soda_id' => (string) Str::uuid7()]);

        $this->postJson(self::ENDPOINT, $this->payload())
            ->assertNotFound()
            ->assertJsonPath('detail', 'La soda no existe.');
    }

    /** The listing is ordered and hides the slots of other sodas. */
    public function test_lists_only_the_slots_of_the_current_soda(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['dia_semana' => 2]))->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload(['apertura' => '13:00', 'cierre' => '17:00']))->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        $otherSoda = SodaModel::factory()->create();
        DB::table('schedules')->insert([
            'soda_id' => $otherSoda->id, 'day_of_week' => 1, 'opens_at' => '05:00', 'closes_at' => '06:00',
        ]);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.apertura', '08:00')
            ->assertJsonPath('data.1.apertura', '13:00')
            ->assertJsonPath('data.2.dia_semana', 2);
    }

    /** Only the owner administers the schedule. */
    public function test_kitchen_staff_is_forbidden_and_visitors_are_unauthenticated(): void
    {
        Sanctum::actingAs(UserModel::factory()->kitchen($this->soda->id)->create(), Role::Kitchen->tokenAbilities());
        $this->postJson(self::ENDPOINT, $this->payload())->assertForbidden();
    }

    /**
     * Build a valid payload with optional overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [...['dia_semana' => 1, 'apertura' => '08:00', 'cierre' => '12:00'], ...$overrides];
    }
}
