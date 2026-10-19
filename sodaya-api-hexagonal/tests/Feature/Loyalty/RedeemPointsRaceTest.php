<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use Illuminate\Support\Facades\DB;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\CommitsRows;
use Tests\Support\Concurrency\Race;
use Tests\TestCase;

final class RedeemPointsRaceTest extends TestCase
{
    use CommitsRows;

    /** A customer with 5 points redeems 3 from two devices at the same instant. */
    public function test_two_redemptions_never_leave_a_negative_balance(): void
    {
        $customer = UserModel::factory()->create();
        $soda = SodaModel::factory()->create();
        $order = $this->committedFor($soda->id, fn (): OrderModel => OrderModel::factory()->delivered()->create([
            'soda_id' => $soda->id,
            'customer_id' => $customer->id,
        ]));
        DB::table('point_movements')->insert(['customer_id' => $customer->id, 'order_id' => $order->id, 'reason' => 'purchase', 'points' => 5]);

        $results = Race::run(base_path('tests/Support/Concurrency/redeem-points.php'), [
            [$customer->id, '3'],
            [$customer->id, '3'],
        ]);

        sort($results);
        $this->assertSame(['insufficient', 'redeemed'], $results);
        $this->assertSame(2, (int) DB::table('point_movements')->where('customer_id', $customer->id)->sum('points'));
    }
}
