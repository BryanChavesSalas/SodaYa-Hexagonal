<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Promotions;

use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Promotions\Infrastructure\Persistence\Models\PromotionModel;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ManagePromotionsTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/cocina/promociones';

    /** Make a fresh soda the current tenant, with its owner. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda();
    }

    /** The owner sees the happy hours by day and start time, and none of another soda. */
    public function test_owner_sees_the_promotions_in_order(): void
    {
        $thursday = $this->promotion(4, '14:00', '15:00', 10);
        $wednesdayLate = $this->promotion(3, '16:00', '17:00', 15);
        $wednesday = $this->promotion(3, '14:00', '15:00', 20);
        PromotionModel::factory()->create();

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $wednesday->id, 'dia' => 3, 'inicio' => '14:00', 'fin' => '15:00', 'porcentaje' => 20],
                ['id' => $wednesdayLate->id, 'dia' => 3, 'inicio' => '16:00', 'fin' => '17:00', 'porcentaje' => 15],
                ['id' => $thursday->id, 'dia' => 4, 'inicio' => '14:00', 'fin' => '15:00', 'porcentaje' => 10],
            ], 'meta' => ['pagina' => 1, 'por_pagina' => 15, 'total' => 3, 'ultima_pagina' => 1]]);
    }

    /** A valid happy hour is stored for the soda and returned. */
    public function test_owner_defines_a_promotion(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->payload());

        $promotionId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertExactJson(['data' => ['id' => $promotionId, 'dia' => 3, 'inicio' => '14:00', 'fin' => '15:00', 'porcentaje' => 20]]);

        $this->assertDatabaseHas('promotions', [
            'id' => $promotionId,
            'soda_id' => $this->soda->id,
            'weekday' => 3,
            'starts_at' => '14:00:00',
            'ends_at' => '15:00:00',
            'percentage' => 20,
        ]);
    }

    /** A happy hour that shares a minute with another of the same day answers 409 promocion-traslapada. */
    public function test_overlapping_promotion_answers_a_conflict(): void
    {
        $this->promotion(3, '14:00', '15:00', 20);

        $this->postJson(self::ENDPOINT, $this->payload(['inicio' => '14:30', 'fin' => '16:00']))
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'http://localhost/problemas/promocion-traslapada')
            ->assertJsonPath('title', 'Promoción traslapada')
            ->assertJsonPath('detail', 'La promoción se traslapa con otra del mismo día.');

        $this->assertDatabaseCount('promotions', 1);
    }

    /** A happy hour may start at the minute another one ends, repeat on another day, or repeat in another soda. */
    #[DataProvider('compatiblePromotions')]
    public function test_touching_and_unrelated_promotions_are_accepted(array $overrides, bool $ofAnotherSoda): void
    {
        if ($ofAnotherSoda) {
            PromotionModel::factory()->create(['weekday' => 3, 'starts_at' => '14:00', 'ends_at' => '15:00']);
        } else {
            $this->promotion(3, '14:00', '15:00', 20);
        }

        $this->postJson(self::ENDPOINT, $this->payload($overrides))->assertCreated();
    }

    /**
     * Payloads that do not collide with a Wednesday happy hour of 14:00 to 15:00.
     *
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function compatiblePromotions(): array
    {
        return [
            'starts when the other ends' => [['inicio' => '15:00', 'fin' => '16:00'], false],
            'same window on thursday' => [['dia' => 4], false],
            'same window as another soda' => [[], true],
        ];
    }

    /** A discount of 95 % is invalid data: the domain decides the range. */
    public function test_discount_out_of_range_is_invalid_data(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['porcentaje' => 95]))
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonPath('detail', 'El descuento debe estar entre 1 % y 90 %.');

        $this->assertDatabaseCount('promotions', 0);
    }

    /** A window that ends when it starts, or before, and a day outside the week are invalid data too. */
    #[DataProvider('invalidValues')]
    public function test_domain_rules_answer_invalid_data(array $overrides, string $detail): void
    {
        $this->postJson(self::ENDPOINT, $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonPath('detail', $detail);

        $this->assertDatabaseCount('promotions', 0);
    }

    /**
     * Payloads with the right shape that the domain refuses, and the message of each one.
     *
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'end equal to start' => [['fin' => '14:00'], 'La hora de inicio debe ser anterior a la de fin.'],
            'end before start' => [['fin' => '13:59'], 'La hora de inicio debe ser anterior a la de fin.'],
            'day after sunday' => [['dia' => 8], 'El día debe estar entre 1 (lunes) y 7 (domingo).'],
            'no discount' => [['porcentaje' => 0], 'El descuento debe estar entre 1 % y 90 %.'],
        ];
    }

    /** Malformed fields are reported one by one in Spanish before reaching the domain. */
    #[DataProvider('malformedPayloads')]
    public function test_malformed_fields_are_reported(string $field, mixed $value, string $message): void
    {
        $this->postJson(self::ENDPOINT, $this->payload([$field => $value]))
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonPath("errores.{$field}.0", $message);
    }

    /**
     * Invalid values for a field and the message each one produces.
     *
     * @return array<string, array{string, mixed, string}>
     */
    public static function malformedPayloads(): array
    {
        return [
            'missing day' => ['dia', null, 'El campo día es obligatorio.'],
            'start without format' => ['inicio', '2pm', 'El campo inicio debe tener el formato H:i.'],
            'end with seconds' => ['fin', '15:00:00', 'El campo fin debe tener el formato H:i.'],
            'percentage that is not a number' => ['porcentaje', 'veinte', 'El campo porcentaje debe ser un número entero.'],
        ];
    }

    /** The kitchen neither sees nor manages the happy hours. */
    public function test_kitchen_has_no_access(): void
    {
        $promotion = $this->promotion(3, '14:00', '15:00', 20);
        $this->actAsStaffOf($this->soda, Role::Kitchen);

        $this->getJson(self::ENDPOINT)->assertForbidden()->assertJsonPath('type', 'http://localhost/problemas/prohibido');
        $this->postJson(self::ENDPOINT, $this->payload(['dia' => 4]))->assertForbidden();
        $this->deleteJson(self::ENDPOINT.'/'.$promotion->id)->assertForbidden();

        $this->assertDatabaseCount('promotions', 1);
    }

    /** The owner removes a happy hour. */
    public function test_owner_deletes_a_promotion(): void
    {
        $promotion = $this->promotion(3, '14:00', '15:00', 20);

        $this->deleteJson(self::ENDPOINT.'/'.$promotion->id)->assertNoContent();

        $this->assertDatabaseCount('promotions', 0);
    }

    /** Missing happy hours and happy hours of other sodas answer 404 and stay in place. */
    public function test_missing_and_foreign_promotions_are_not_found(): void
    {
        $foreign = PromotionModel::factory()->create();

        foreach ([$foreign->id, '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99'] as $promotionId) {
            $this->deleteJson(self::ENDPOINT.'/'.$promotionId)
                ->assertNotFound()
                ->assertJsonPath('detail', 'La promoción no existe.');
        }

        $this->scopeQueriesTo($foreign->soda_id);
        $this->assertDatabaseHas('promotions', ['id' => $foreign->id]);
    }

    /** Create a happy hour of the current soda. */
    private function promotion(int $weekday, string $startsAt, string $endsAt, int $percentage): PromotionModel
    {
        return PromotionModel::factory()->create([
            'soda_id' => $this->soda->id,
            'weekday' => $weekday,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'percentage' => $percentage,
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
        return ['dia' => 3, 'inicio' => '14:00', 'fin' => '15:00', 'porcentaje' => 20, ...$overrides];
    }
}
