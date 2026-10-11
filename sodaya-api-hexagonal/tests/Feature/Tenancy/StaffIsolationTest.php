<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class StaffIsolationTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private SodaModel $otherSoda;

    /** @var array<string, Model> */
    private array $own;

    /** @var array<string, Model> */
    private array $foreign;

    /** Give the authenticated owner and another soda one row of each kind on a Monday morning. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 09:00:00');
        $this->actOnNewSoda();
        $this->otherSoda = SodaModel::factory()->create();

        $this->own = $this->rowsOf($this->soda, 'Bebidas', 'Fresco de cas', 2, '2026-12-25');
        $this->foreign = $this->rowsOf($this->otherSoda, 'Casados', 'Casado de pollo', 1, '2026-10-05');
    }

    /** A staff listing shows the rows of the own soda and none of the other. */
    #[DataProvider('listings')]
    public function test_listing_shows_only_the_rows_of_the_own_soda(string $endpoint, string $kind): void
    {
        $this->getJson($endpoint)
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->own[$kind]->getKey()]);
    }

    /**
     * Listing endpoints paired with the kind of row they show.
     *
     * @return array<string, array{string, string}>
     */
    public static function listings(): array
    {
        return [
            'list dishes' => ['/api/v1/cocina/platos', 'dish'],
            'list categories' => ['/api/v1/cocina/categorias', 'category'],
            'list opening hours' => ['/api/v1/cocina/horario', 'slot'],
            'list closures' => ['/api/v1/cocina/cierres', 'closure'],
        ];
    }

    /**
     * Create a category, a dish in it, a time slot and a closure for a soda.
     *
     * @return array<string, Model>
     */
    private function rowsOf(SodaModel $soda, string $category, string $dish, int $day, string $closedOn): array
    {
        $categoryRow = CategoryModel::factory()->create(['soda_id' => $soda->id, 'name' => $category]);

        return [
            'category' => $categoryRow,
            'dish' => DishModel::factory()->create([
                'soda_id' => $soda->id,
                'category_id' => $categoryRow->id,
                'name' => $dish,
            ]),
            'slot' => TimeSlotModel::factory()->create([
                'soda_id' => $soda->id,
                'day_of_week' => $day,
                'opens_at' => '08:00',
                'closes_at' => '12:00',
            ]),
            'closure' => ClosureModel::factory()->create(['soda_id' => $soda->id, 'closed_on' => $closedOn]),
        ];
    }
}
