<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class CatalogSchemaTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** The database generates the identifier when the application sends none. */
    public function test_database_generates_the_identifier(): void
    {
        DB::table('sodas')->insert(['name' => 'Soda sin identificador']);

        $this->assertTrue(Str::isUuid(DB::table('sodas')->value('id'), 7));
    }

    /** Values outside the business ranges are rejected by the database. */
    #[DataProvider('outOfRangeValues')]
    public function test_database_rejects_out_of_range_values(string $column, int $value): void
    {
        $this->expectException(QueryException::class);

        $this->insertDish($this->insertSoda(), [$column => $value]);
    }

    /**
     * Columns paired with a value just outside their allowed range.
     *
     * @return array<string, array{string, int}>
     */
    public static function outOfRangeValues(): array
    {
        return [
            'price below minimum' => ['price', 99],
            'price above maximum' => ['price', 100_001],
            'no preparation time' => ['preparation_minutes', 0],
            'preparation time above maximum' => ['preparation_minutes', 121],
            'negative portions' => ['available_portions', -1],
            'portions above maximum' => ['available_portions', 501],
        ];
    }

    /** Two dishes of the same soda cannot share a name. */
    public function test_dish_name_is_unique_within_a_soda(): void
    {
        $sodaId = $this->insertSoda();
        $this->insertDish($sodaId, ['name' => 'Casado']);

        $this->expectException(QueryException::class);

        $this->insertDish($sodaId, ['name' => 'Casado']);
    }

    /** Different sodas may use the same dish name. */
    public function test_dish_name_can_repeat_across_sodas(): void
    {
        $this->insertDish($this->insertSoda(), ['name' => 'Casado']);
        $this->insertDish($this->insertSoda(), ['name' => 'Casado']);

        $this->assertSame(2, DB::table('dishes')->where('name', 'Casado')->count());
    }

    /** A dish cannot reference a category owned by another soda. */
    public function test_dish_cannot_use_a_category_of_another_soda(): void
    {
        $foreignCategoryId = $this->insertCategory($this->insertSoda());

        $this->expectException(QueryException::class);

        $this->insertDish($this->insertSoda(), ['category_id' => $foreignCategoryId]);
    }

    /** Deleting a category keeps its dishes without a category. */
    public function test_deleting_a_category_keeps_its_dishes(): void
    {
        $sodaId = $this->insertSoda();
        $categoryId = $this->insertCategory($sodaId);
        $dishId = $this->insertDish($sodaId, ['category_id' => $categoryId]);

        DB::table('categories')->where('id', $categoryId)->delete();

        $dish = DB::table('dishes')->where('id', $dishId)->sole();
        $this->assertNull($dish->category_id);
        $this->assertSame($sodaId, $dish->soda_id);
    }

    /** Insert a soda and return its identifier. */
    private function insertSoda(): string
    {
        $id = (string) Str::uuid7();

        DB::table('sodas')->insert(['id' => $id, 'name' => 'Soda de prueba']);

        return $id;
    }

    /** Insert a category for the soda and return its identifier. */
    private function insertCategory(string $sodaId): string
    {
        $id = (string) Str::uuid7();

        DB::table('categories')->insert(['id' => $id, 'soda_id' => $sodaId, 'name' => 'Casados']);

        return $id;
    }

    /**
     * Insert a dish for the soda and return its identifier.
     *
     * @param  array<string, int|string|null>  $overrides
     */
    private function insertDish(string $sodaId, array $overrides = []): string
    {
        $id = (string) Str::uuid7();

        DB::table('dishes')->insert([
            'id' => $id,
            'soda_id' => $sodaId,
            'name' => 'Casado de pollo',
            'price' => 2_800,
            'preparation_minutes' => 15,
            'available_portions' => 10,
            ...$overrides,
        ]);

        return $id;
    }
}
