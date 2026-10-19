<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Dishes;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Catalog\Dishes\Application\UseCases\ResetDailyPortions;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\CommitsRows;
use Tests\TestCase;

final class ResetDailyPortionsTest extends TestCase
{
    use CommitsRows;

    /** Two sodas end the day with portions far from their base: one dish sold out, one below its base, one above it. */
    protected function setUp(): void
    {
        parent::setUp();

        $esquina = SodaModel::factory()->create();
        $rincon = SodaModel::factory()->create();

        $this->dish($esquina, 'Casado de pollo', available: 2, base: 20);
        $this->dish($esquina, 'Olla de carne', available: 30, base: 25);
        $this->dish($rincon, 'Chifrijo', available: 0, base: 12);
    }

    /** Every dish of every soda gets back its base portions. */
    public function test_every_soda_gets_back_its_base_portions(): void
    {
        app(ResetDailyPortions::class)->execute();

        $this->assertSame(['Casado de pollo' => 20, 'Chifrijo' => 12, 'Olla de carne' => 25], $this->availablePortions());
    }

    /** The application role cannot run the procedure: it reaches every soda. */
    public function test_application_role_cannot_run_the_procedure(): void
    {
        try {
            DB::statement('CALL sp_reset_daily_portions()');
            $this->fail('The application role ran the procedure.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->getCode());
        }
    }

    /** The procedure commits soda by soda, so it cannot run inside a transaction. */
    public function test_procedure_runs_outside_a_transaction(): void
    {
        try {
            DB::connection('pgsql_maintenance')->transaction(
                fn () => DB::connection('pgsql_maintenance')->statement('CALL sp_reset_daily_portions()'),
            );
            $this->fail('The procedure committed inside a transaction.');
        } catch (QueryException $exception) {
            $this->assertSame('2D000', $exception->getCode());
            $this->assertSame(['Casado de pollo' => 2, 'Chifrijo' => 0, 'Olla de carne' => 30], $this->availablePortions());
        }
    }

    /** Create a committed dish of a soda. */
    private function dish(SodaModel $soda, string $name, int $available, int $base): void
    {
        $this->committedFor($soda->id, fn (): DishModel => DishModel::factory()->create([
            'soda_id' => $soda->id,
            'name' => $name,
            'available_portions' => $available,
            'base_portions' => $base,
        ]));
    }

    /**
     * Portions left of each dish, read with the maintenance role, which sees every soda.
     *
     * @return array<string, int>
     */
    private function availablePortions(): array
    {
        return DB::connection('pgsql_maintenance')->table('dishes')
            ->orderBy('name')
            ->pluck('available_portions', 'name')
            ->map(fn (mixed $portions): int => (int) $portions)
            ->all();
    }
}
