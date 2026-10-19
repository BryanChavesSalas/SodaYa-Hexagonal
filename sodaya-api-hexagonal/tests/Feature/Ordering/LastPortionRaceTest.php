<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Support\Facades\DB;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\CommitsRows;
use Tests\Support\Concurrency\Race;
use Tests\TestCase;

final class LastPortionRaceTest extends TestCase
{
    use CommitsRows;

    /** Two customers of the same soda ask for its last casado at the same instant. */
    public function test_two_orders_for_the_last_portion_end_with_one_placed_and_one_sold_out(): void
    {
        $soda = SodaModel::factory()->create();
        $customers = UserModel::factory()->count(2)->create();
        $dish = $this->committedFor($soda->id, fn (): DishModel => DishModel::factory()->create([
            'soda_id' => $soda->id,
            'available_portions' => 1,
        ]));

        $results = Race::run(base_path('tests/Support/Concurrency/place-order.php'), [
            [$soda->id, $customers[0]->id, $dish->id],
            [$soda->id, $customers[1]->id, $dish->id],
        ]);

        sort($results);
        $this->assertSame(['placed', 'sold-out'], $results);

        $this->committedFor($soda->id, function () use ($dish): void {
            $this->assertSame(0, $dish->refresh()->available_portions);
            $this->assertSame(1, DB::table('orders')->count());
        });
    }
}
