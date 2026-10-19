<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Ordering\Orders\Infrastructure\Catalog\EloquentOrderableDishes;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class EloquentOrderableDishesTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    /** The requested dishes of the soda come back with their current name and price, active or not; a dish of another soda is missing. */
    public function test_reads_the_requested_dishes_of_the_soda_only(): void
    {
        $soda = SodaModel::factory()->create();
        $casado = DishModel::factory()->create(['soda_id' => $soda->id, 'name' => 'Casado de pollo', 'price' => 2_800]);
        $retired = DishModel::factory()->inactive()->create(['soda_id' => $soda->id]);
        DishModel::factory()->create(['soda_id' => $soda->id]);
        $foreign = DishModel::factory()->create(['name' => 'Casado de pollo']);
        $this->scopeQueriesTo($soda->id);

        $dishes = new EloquentOrderableDishes()->ofSoda(new SodaId($soda->id), [$casado->id, $retired->id, $foreign->id]);

        $this->assertEqualsCanonicalizing([$casado->id, $retired->id], array_keys($dishes));
        $this->assertSame('Casado de pollo', $dishes[$casado->id]->name);
        $this->assertSame(2_800, $dishes[$casado->id]->unitPrice);
        $this->assertTrue($dishes[$casado->id]->active);
        $this->assertFalse($dishes[$retired->id]->active);
    }

    /** Without identifiers nothing is read. */
    public function test_reads_nothing_without_identifiers(): void
    {
        $soda = SodaModel::factory()->create();
        DishModel::factory()->create(['soda_id' => $soda->id]);
        $this->scopeQueriesTo($soda->id);

        $this->assertSame([], new EloquentOrderableDishes()->ofSoda(new SodaId($soda->id), []));
    }
}
