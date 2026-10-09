<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\Closures;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Shared\Infrastructure\Http\Problem\ProblemDetailsRenderer;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ManageClosuresTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/cocina/cierres';

    private const string TODAY = '2026-10-07';

    /** Make a fresh soda the current tenant and fix the clock in Costa Rica. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda();
        $this->travelTo(Carbon::parse(self::TODAY.' 13:45', 'America/Costa_Rica'));
    }

    /** A closure for a date is stored and returned with its location. */
    public function test_owner_registers_a_closure(): void
    {
        $response = $this->postJson(self::ENDPOINT, ['fecha' => '2026-12-25', 'motivo' => 'Navidad']);

        $closureId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertHeader('Location', url(self::ENDPOINT.'/'.$closureId))
            ->assertExactJson(['data' => [
                'id' => $closureId,
                'fecha' => '2026-12-25',
                'motivo' => 'Navidad',
            ]]);

        $this->assertDatabaseHas('closures', [
            'id' => $closureId,
            'soda_id' => $this->soda->id,
            'closed_on' => '2026-12-25',
            'reason' => 'Navidad',
        ]);
    }

    /** The reason is optional. */
    public function test_reason_is_optional(): void
    {
        $this->postJson(self::ENDPOINT, ['fecha' => self::TODAY])
            ->assertCreated()
            ->assertJsonPath('data.fecha', self::TODAY)
            ->assertJsonPath('data.motivo', null);
    }

    /** The closure belongs to the current soda even if the payload names another. */
    public function test_soda_sent_in_the_payload_is_ignored(): void
    {
        $otherSoda = SodaModel::factory()->create();

        $this->postJson(self::ENDPOINT, ['fecha' => '2026-12-25', 'soda_id' => $otherSoda->id])->assertCreated();

        $this->assertDatabaseHas('closures', ['soda_id' => $this->soda->id]);
        $this->assertDatabaseMissing('closures', ['soda_id' => $otherSoda->id]);
    }

    /** Invalid fields are reported one by one in Spanish. */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_data_is_rejected(string $field, mixed $value, string $message): void
    {
        $this->postJson(self::ENDPOINT, [...['fecha' => '2026-12-25'], $field => $value])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', ProblemDetailsRenderer::CONTENT_TYPE)
            ->assertJsonPath("errores.{$field}.0", $message);

        $this->assertDatabaseCount('closures', 0);
    }

    /**
     * Invalid values for a field and the message each one produces.
     *
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing date' => ['fecha', null, 'El campo fecha es obligatorio.'],
            'date in another format' => ['fecha', '25/12/2026', 'El campo fecha debe tener el formato Y-m-d.'],
            'past date' => ['fecha', '2026-10-06', 'La fecha no puede ser anterior a hoy.'],
            'reason too long' => ['motivo', str_repeat('a', 201), 'El campo motivo no debe tener más de 200 caracteres.'],
        ];
    }

    /** A second closure on the same date is a conflict. */
    public function test_second_closure_on_the_same_date_is_a_conflict(): void
    {
        $this->postJson(self::ENDPOINT, ['fecha' => '2026-12-25'])->assertCreated();

        $this->postJson(self::ENDPOINT, ['fecha' => '2026-12-25'])
            ->assertConflict()
            ->assertHeader('Content-Type', ProblemDetailsRenderer::CONTENT_TYPE)
            ->assertJsonPath('detail', 'La soda ya tiene un cierre registrado para esa fecha.');

        $this->assertDatabaseCount('closures', 1);
    }

    /** Another soda may close on the same date. */
    public function test_date_closed_by_another_soda_is_accepted(): void
    {
        ClosureModel::factory()->create(['closed_on' => '2026-12-25']);

        $this->postJson(self::ENDPOINT, ['fecha' => '2026-12-25'])->assertCreated();
    }

    /** The owner closes the soda for the rest of today. */
    public function test_owner_closes_for_the_rest_of_today(): void
    {
        $this->postJson(self::ENDPOINT.'/hoy', ['motivo' => 'Se fue la luz'])
            ->assertCreated()
            ->assertJsonPath('data.fecha', self::TODAY)
            ->assertJsonPath('data.motivo', 'Se fue la luz');

        $this->postJson(self::ENDPOINT.'/hoy')
            ->assertConflict()
            ->assertJsonPath('detail', 'La soda ya tiene un cierre registrado para esa fecha.');
    }

    /** Today is the date in Costa Rica, even when it is already tomorrow in UTC. */
    public function test_today_is_taken_in_costa_rica(): void
    {
        $this->travelTo(Carbon::parse(self::TODAY.' 23:30', 'America/Costa_Rica'));

        $this->postJson(self::ENDPOINT.'/hoy')->assertCreated()->assertJsonPath('data.fecha', self::TODAY);
    }

    /** The reason for closing today is limited in length. */
    public function test_reason_for_today_is_validated(): void
    {
        $this->postJson(self::ENDPOINT.'/hoy', ['motivo' => str_repeat('a', 201)])
            ->assertUnprocessable()
            ->assertJsonPath('errores.motivo.0', 'El campo motivo no debe tener más de 200 caracteres.');
    }

    /** The listing shows the closures of the soda from today onward, in date order. */
    public function test_listing_shows_upcoming_closures_in_order(): void
    {
        $this->closure('2026-12-25');
        $this->closure(self::TODAY);
        $this->closure('2026-11-02');
        $this->closure('2026-10-01');
        ClosureModel::factory()->create(['closed_on' => '2026-10-20']);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.*.fecha', [self::TODAY, '2026-11-02', '2026-12-25']);
    }

    /** The listing keeps working once a stored closure is in the past. */
    public function test_listing_works_after_a_closure_date_passes(): void
    {
        $this->postJson(self::ENDPOINT.'/hoy')->assertCreated();
        $this->postJson(self::ENDPOINT, ['fecha' => '2026-10-08'])->assertCreated();

        $this->travelTo(Carbon::parse('2026-10-08 08:00', 'America/Costa_Rica'));

        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('data.*.fecha', ['2026-10-08']);
    }

    /** A closure is deleted, even after its date has passed. */
    public function test_owner_deletes_a_closure(): void
    {
        $closure = $this->closure('2026-10-01');

        $this->deleteJson(self::ENDPOINT.'/'.$closure->id)->assertNoContent();

        $this->assertDatabaseMissing('closures', ['id' => $closure->id]);
    }

    /** A closure of another soda cannot be deleted and looks missing. */
    public function test_closure_of_another_soda_is_not_found(): void
    {
        $foreign = ClosureModel::factory()->create();

        $this->deleteJson(self::ENDPOINT.'/'.$foreign->id)
            ->assertNotFound()
            ->assertHeader('Content-Type', ProblemDetailsRenderer::CONTENT_TYPE)
            ->assertJsonPath('detail', 'El cierre no existe.');

        $this->assertDatabaseHas('closures', ['id' => $foreign->id]);
    }

    /** A malformed identifier does not reach the controller. */
    public function test_malformed_identifier_is_not_found(): void
    {
        $this->deleteJson(self::ENDPOINT.'/abc')->assertNotFound();
    }

    /** Store a closure of the current soda directly in the database. */
    private function closure(string $date): ClosureModel
    {
        return ClosureModel::factory()->create(['soda_id' => $this->soda->id, 'closed_on' => $date]);
    }
}
