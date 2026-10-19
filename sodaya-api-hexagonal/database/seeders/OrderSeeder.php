<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;

final class OrderSeeder extends Seeder
{
    /** Seed three orders of the demo customer at the demo soda, once: delivered, confirmed and cancelled. */
    public function run(SodaTransaction $transaction): void
    {
        $customer = UserModel::query()->where('email', CustomerSeeder::EMAIL)->firstOrFail();

        $transaction->run(CatalogSeeder::DEMO_SODA_ID, function () use ($customer): void {
            if (OrderModel::query()->exists()) {
                return;
            }

            $dishes = DishModel::query()->whereIn('name', ['Casado de pollo', 'Fresco de cas'])->orderBy('name')->get();
            $orders = [
                OrderModel::factory()->delivered(),
                OrderModel::factory()->confirmed(),
                OrderModel::factory()->cancelled(),
            ];

            foreach ($orders as $factory) {
                $order = $factory->create(['soda_id' => CatalogSeeder::DEMO_SODA_ID, 'customer_id' => $customer->id]);

                foreach ($dishes as $quantity => $dish) {
                    OrderLineModel::factory()->for($order, 'order')->create([
                        'dish_id' => $dish->id,
                        'dish_name' => $dish->name,
                        'unit_price' => $dish->price,
                        'quantity' => $quantity + 1,
                    ]);
                }
            }
        });
    }
}
