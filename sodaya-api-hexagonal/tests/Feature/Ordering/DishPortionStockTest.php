<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Ordering\Orders\Domain\Entities\OrderLine;
use Src\Ordering\Orders\Domain\ValueObjects\DishId;
use Src\Ordering\Orders\Domain\ValueObjects\Quantity;
use Src\Ordering\Orders\Infrastructure\Catalog\DishPortionStock;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class DishPortionStockTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private SodaModel $soda;

    private DishModel $casado;

    private DishModel $fresco;

    /** A soda with 3 casados and 10 frescos left. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->soda = SodaModel::factory()->create();
        $this->casado = DishModel::factory()->create(['soda_id' => $this->soda->id, 'available_portions' => 3]);
        $this->fresco = DishModel::factory()->create(['soda_id' => $this->soda->id, 'available_portions' => 10]);
    }

    /** When every dish has enough, every line takes its portions, down to the last one. */
    public function test_reserves_every_line(): void
    {
        $this->assertTrue($this->reserve([$this->casado->id => 3, $this->fresco->id => 2]));

        $this->assertSame(0, $this->casado->refresh()->available_portions);
        $this->assertSame(8, $this->fresco->refresh()->available_portions);
    }

    /** When one dish falls short, no dish loses portions. */
    public function test_one_short_line_reserves_nothing(): void
    {
        $this->assertFalse($this->reserve([$this->fresco->id => 2, $this->casado->id => 4]));

        $this->assertSame(3, $this->casado->refresh()->available_portions);
        $this->assertSame(10, $this->fresco->refresh()->available_portions);
    }

    /** A dish that is off the menu, of another soda or missing cannot be reserved. */
    #[DataProvider('unavailableDishes')]
    public function test_unavailable_dish_is_not_reserved(string $case): void
    {
        $dishId = match ($case) {
            'inactive' => DishModel::factory()->inactive()->create(['soda_id' => $this->soda->id, 'available_portions' => 5])->id,
            'foreign' => DishModel::factory()->create(['available_portions' => 5])->id,
            default => '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99',
        };
        $this->scopeQueriesTo($this->soda->id);

        $this->assertFalse($this->reserve([$this->fresco->id => 1, $dishId => 1]));
        $this->assertSame(10, $this->fresco->refresh()->available_portions);
    }

    /**
     * Dishes the soda cannot sell.
     *
     * @return array<string, array{string}>
     */
    public static function unavailableDishes(): array
    {
        return ['inactive' => ['inactive'], 'of another soda' => ['foreign'], 'missing' => ['missing']];
    }

    /**
     * Reserve the given quantities of each dish for the soda.
     *
     * @param  array<string, int>  $quantities
     */
    private function reserve(array $quantities): bool
    {
        $lines = [];

        foreach ($quantities as $dishId => $quantity) {
            $lines[] = new OrderLine(new DishId($dishId), 'Plato', 1_000, new Quantity($quantity));
        }

        return new DishPortionStock()->reserve(new SodaId($this->soda->id), $lines);
    }
}
